<?php
// Sécurité : ne charger que sous WP-CLI
if ( defined('WP_CLI') && WP_CLI ) {

    /**
     * Recalcule les usages d’images, exporte les résultats, et gère les orphelines.
     *
     * ## EXEMPLES
     *
     *   # Recalculer toutes les images par batch de 400
     *   wp dnc media recalc --all --batch=400
     *
     *   # Recalculer des IDs précis
     *   wp dnc media recalc --ids=123,456,789
     *
     *   # Exporter en CSV (usages + orphelines)
     *   wp dnc media recalc --all --export=/tmp/dnc-usages.csv
     *
     *   # Lister les orphelines
     *   wp dnc media recalc --orphans
     *
     *   # Supprimer les orphelines
     *   wp dnc media recalc --orphans --delete-orphans
     *
     *   # Forcer un HEAD rapide à 1s lors du test 404 distants
     *   wp dnc media recalc --all --http-timeout=1
     *
     * @when after_wp_load
     */
    class DNC_Media_Usage_Command {

        /**
         * Lance le traitement.
         *
         * ## OPTIONS
         *
         * [--all]
         * : Traiter toutes les images (attachment image/*).
         *
         * [--ids=<ids>]
         * : Liste d’IDs séparés par des virgules (ex: 12,34,56).
         *
         * [--batch=<n>]
         * : Taille du lot (par défaut 300).
         *
         * [--export=<chemin>]
         * : Chemin d’un fichier CSV à générer (usages + orphelines).
         *
         * [--orphans]
         * : Mode détection des photos orphelines uniquement (ne recalcule pas les usages).
         *
         * [--delete-orphans]
         * : Supprimer les orphelines détectées (à utiliser avec --orphans).
         *
         * [--http-timeout=<sec>]
         * : Timeout pour wp_remote_head lors des vérifs 404 distantes (défaut 2).
         *
         * [--quiet]
         * : Mode silencieux (moins de logs).
         */
        public function recalc( $args, $assoc_args ) {
            $all            = \WP_CLI\Utils\get_flag_value($assoc_args, 'all', false);
            $ids_arg        = \WP_CLI\Utils\get_flag_value($assoc_args, 'ids', '');
            $batch_size     = (int) (\WP_CLI\Utils\get_flag_value($assoc_args, 'batch', 300));
            $export_path    = \WP_CLI\Utils\get_flag_value($assoc_args, 'export', '');
            $only_orphans   = \WP_CLI\Utils\get_flag_value($assoc_args, 'orphans', false);
            $delete_orphans = \WP_CLI\Utils\get_flag_value($assoc_args, 'delete-orphans', false);
            $http_timeout   = (int) (\WP_CLI\Utils\get_flag_value($assoc_args, 'http-timeout', 2));
            $quiet          = \WP_CLI\Utils\get_flag_value($assoc_args, 'quiet', false);

            if ( ! $all && empty($ids_arg) && ! $only_orphans ) {
                \WP_CLI::error("Spécifie --all ou --ids=… (ou --orphans).");
            }

            // Sécurise les helpers requis (issus de ta page admin)
            if ( ! function_exists('dnc_get_image_usage_breakdown') || ! function_exists('dnc_is_attachment_missing') ) {
                \WP_CLI::error("Fonctions dnc_* manquantes. Assure-toi d’avoir inclus le fichier où elles sont définies.");
            }

            // Override soft du timeout HEAD pour la détection 404
            add_filter('http_request_args', function($r, $url) use ($http_timeout){
                if (!isset($r['timeout']) || $r['timeout'] > $http_timeout) {
                    $r['timeout'] = $http_timeout;
                }
                return $r;
            }, 10, 2);

            // Collecte des IDs à traiter
            $ids = [];
            if ( $all || $only_orphans ) {
                $ids = $this->query_all_image_ids();
            }
            if ( ! empty($ids_arg) ) {
                $manual = array_filter(array_map('absint', explode(',', $ids_arg)));
                $ids = array_unique(array_merge($ids, $manual));
            }
            if ( empty($ids) ) {
                \WP_CLI::warning("Aucune image à traiter.");
                return;
            }

            // Prépare export CSV si demandé
            $csv = null;
            if ( ! empty($export_path) ) {
                $dir = dirname($export_path);
                if ( ! is_dir($dir) ) {
                    \WP_CLI::error("Dossier inexistant pour l’export CSV: {$dir}");
                }
                $csv = fopen($export_path, 'w');
                if ( ! $csv ) {
                    \WP_CLI::error("Impossible d’ouvrir le CSV en écriture: {$export_path}");
                }
                fputcsv($csv, [
                    'attachment_id',
                    'file_url',
                    'is_orphan',
                    'usage_total',
                    'posts_total',
                    'posts_content',
                    'postmeta',
                    'thumbnails',
                    'termmeta',
                    'options',
                ]);
            }

            // Optimisations mémoire/compteurs
            wp_suspend_cache_invalidation(true);
            wp_defer_term_counting(true);
            wp_defer_comment_counting(true);

            $total = count($ids);
            $progress = \WP_CLI\Utils\make_progress_bar(
                $only_orphans ? 'Scan orphelines' : 'Recalcul usages',
                $total
            );

            $done = 0;
            $deleted = 0;

            // Traitement par batches
            foreach ( array_chunk($ids, $batch_size) as $chunk ) {
                // Evite de garder trop de choses en mémoire
                wp_cache_flush();

                foreach ($chunk as $att_id) {
                    $is_orphan = $this->is_orphan_safe($att_id);

                    if ( $only_orphans ) {
                        if ( $delete_orphans && $is_orphan ) {
                            $ok = wp_delete_attachment($att_id, true);
                            if ( $ok ) {
                                $deleted++;
                                if (!$quiet) \WP_CLI::log("🗑️  Orpheline supprimée #{$att_id}");
                            } else {
                                if (!$quiet) \WP_CLI::warning("Échec suppression orpheline #{$att_id}");
                            }
                        }

                        if ( $csv ) {
                            $url = wp_get_attachment_url($att_id);
                            fputcsv($csv, [
                                $att_id,
                                $url,
                                $is_orphan ? 1 : 0,
                                '', '', '', '', '', '',
                            ]);
                        }
                    } else {
                        // Recalc usage + purge du cache
                        delete_transient('dnc_usage_breakdown_' . $att_id);
                        $b = dnc_get_image_usage_breakdown($att_id, true);

                        if ( $csv ) {
                            $url = wp_get_attachment_url($att_id);
                            fputcsv($csv, [
                                $att_id,
                                $url,
                                $is_orphan ? 1 : 0,
                                (int)$b['total'],
                                (int)$b['posts_total'],
                                (int)$b['by_source']['posts_content'],
                                (int)$b['by_source']['postmeta'],
                                (int)$b['by_source']['thumbnails'],
                                (int)$b['by_source']['termmeta'],
                                (int)$b['by_source']['options'],
                            ]);
                        }

                        if (!$quiet) {
                            \WP_CLI::log(sprintf(
                                '#%d => total:%d (posts:%d | content:%d, meta:%d, thumb:%d, term:%d, opt:%d)%s',
                                $att_id,
                                (int)$b['total'],
                                (int)$b['posts_total'],
                                (int)$b['by_source']['posts_content'],
                                (int)$b['by_source']['postmeta'],
                                (int)$b['by_source']['thumbnails'],
                                (int)$b['by_source']['termmeta'],
                                (int)$b['by_source']['options'],
                                $is_orphan ? ' [ORPHELINE]' : ''
                            ));
                        }
                    }

                    $done++;
                    $progress->tick();
                }
            }

            $progress->finish();

            // Restaure
            wp_defer_comment_counting(false);
            wp_defer_term_counting(false);
            wp_suspend_cache_invalidation(false);

            if ( $csv ) {
                fclose($csv);
                \WP_CLI::success("CSV écrit : {$export_path}");
            }

            if ( $only_orphans ) {
                if ( $delete_orphans ) {
                    \WP_CLI::success("Orphelines supprimées : {$deleted} / {$done}");
                } else {
                    \WP_CLI::success("Orphelines scannées : {$done}");
                }
            } else {
                \WP_CLI::success("Images recalculées : {$done}");
            }
        }

        /**
         * Récupère tous les IDs d’attachments image/*, via WP_Query (fields=ids).
         * @return int[]
         */
        private function query_all_image_ids() : array {
            $ids = [];
            $paged = 1;
            do {
                $q = new WP_Query([
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'post_status'    => 'inherit',
                    'posts_per_page' => 500,
                    'paged'          => $paged,
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ]);
                if ( $q->have_posts() ) {
                    $ids = array_merge($ids, $q->posts);
                    $paged++;
                } else {
                    break;
                }
                // Libère la mémoire
                wp_reset_postdata();
            } while ( true );

            return array_map('absint', array_unique($ids));
        }

        /**
         * Test orpheline en attrapant les erreurs sans fatal.
         * @return bool
         */
        private function is_orphan_safe( int $att_id ) : bool {
            try {
                return (bool) dnc_is_attachment_missing($att_id);
            } catch (\Throwable $e) {
                \WP_CLI::warning("Erreur dnc_is_attachment_missing(#{$att_id}) : " . $e->getMessage());
                return false;
            }
        }
    }

    \WP_CLI::add_command('dnc media', 'DNC_Media_Usage_Command');
}
