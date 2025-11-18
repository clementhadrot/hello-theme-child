<?php
/**
 * Dashboard Header PRO – Universel
 * Fonctionne en mutualisé, VPS, dédié, container, Plesk, cPanel…
 * Affiche uniquement ce que le serveur permet de détecter.
 */

if (!defined('ABSPATH')) exit;

/* ----------------------------------------------------------
   1. DÉTECTION DES MÉTRIQUES DISPONIBLES
---------------------------------------------------------- */

/**
 * CPU usage (si /proc/stat dispo)
 */
function dnc_get_cpu_usage() {
    if (!is_readable('/proc/stat')) return false;

    $stat1 = file('/proc/stat');
    usleep(200000); // 200ms
    $stat2 = file('/proc/stat');

    if (!$stat1 || !$stat2) return false;

    $cpu1 = explode(' ', preg_replace('!\s+!', ' ', $stat1[0]));
    $cpu2 = explode(' ', preg_replace('!\s+!', ' ', $stat2[0]));

    $diff = array(
        'user'   => $cpu2[1] - $cpu1[1],
        'nice'   => $cpu2[2] - $cpu1[2],
        'system' => $cpu2[3] - $cpu1[3],
        'idle'   => $cpu2[4] - $cpu1[4]
    );

    $total = array_sum($diff);
    if ($total <= 0) return false;

    $cpu_usage = 100 - (($diff['idle'] / $total) * 100);
    return round($cpu_usage, 1);
}

/**
 * RAM usage server (si /proc/meminfo)
 */
function dnc_get_server_ram() {
    if (!is_readable('/proc/meminfo')) return false;

    $meminfo = file('/proc/meminfo');
    $data = [];

    foreach ($meminfo as $line) {
        list($key, $val) = explode(':', $line);
        $data[$key] = filter_var($val, FILTER_SANITIZE_NUMBER_INT);
    }

    if (!isset($data['MemTotal']) || !isset($data['MemAvailable'])) return false;

    $total = $data['MemTotal'] * 1024;
    $available = $data['MemAvailable'] * 1024;
    $used = $total - $available;

    return [
        'total' => $total,
        'used'  => $used,
        'percent' => round(($used / $total) * 100, 1)
    ];
}

/**
 * Load Average (universel)
 */
function dnc_get_load_average() {
    if (!function_exists('sys_getloadavg')) return false;
    $load = sys_getloadavg();
    return isset($load[0]) ? round($load[0], 2) : false;
}

/**
 * Uptime serveur (/proc/uptime)
 */
function dnc_get_server_uptime() {
    if (!is_readable('/proc/uptime')) return false;
    $uptime = explode(' ', file_get_contents('/proc/uptime'))[0];
    if (!$uptime) return false;

    $days = floor($uptime / 86400);
    $hours = floor(($uptime % 86400) / 3600);

    return $days . "j " . $hours . "h";
}

/**
 * Mémoire PHP & SQL – toujours disponibles
 */
function dnc_get_wp_runtime_metrics() {
    global $wpdb;

    return [
        'php_memory' => round(memory_get_usage() / 1024 / 1024, 2),
        'php_memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2),
        'queries'   => get_num_queries(),
        'load_time' => timer_stop(4)
    ];
}

/* ----------------------------------------------------------
   2. RENDU HTML DU DASHBOARD HEADER
---------------------------------------------------------- */
function dnc_render_dashboard_header() {

    $cpu = dnc_get_cpu_usage();
    $ram = dnc_get_server_ram();
    $load = dnc_get_load_average();
    $uptime = dnc_get_server_uptime();
    $runtime = dnc_get_wp_runtime_metrics();

    ?>
    <div class="dnc-dashboard-header">
        
        <?php if ($cpu !== false): ?>
        <div class="dnc-card">
            <div class="dnc-gauge" data-value="<?php echo esc_attr($cpu); ?>"></div>
            <div class="dnc-card-info">
                <h4>CPU</h4>
                <p><?php echo $cpu; ?>%</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ram !== false): ?>
        <div class="dnc-card">
            <div class="dnc-gauge" data-value="<?php echo esc_attr($ram['percent']); ?>"></div>
            <div class="dnc-card-info">
                <h4>RAM</h4>
                <p><?php echo $ram['used']/1024/1024; ?> Mo /
                   <?php echo $ram['total']/1024/1024; ?> Mo</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($load !== false): ?>
        <div class="dnc-card">
            <div class="dnc-gauge" data-value="<?php echo min(100, $load * 20); ?>"></div>
            <div class="dnc-card-info">
                <h4>Load Avg</h4>
                <p><?php echo $load; ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($uptime !== false): ?>
        <div class="dnc-card">
            <div class="dnc-badge"><?php echo $uptime; ?></div>
            <div class="dnc-card-info">
                <h4>Uptime</h4>
                <p>Serveur</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- TOUJOURS dispo : PHP -->
        <div class="dnc-card" data-spark="load_time">
            <div class="dnc-badge"><?php echo $runtime['load_time']; ?>s</div>
            <div class="dnc-card-info">
                <h4>Temps WP</h4>
                <p>Génération</p>
            </div>
        </div>

        <div class="dnc-card" data-spark="sql_queries">
            <div class="dnc-badge"><?php echo $runtime['queries']; ?></div>
            <div class="dnc-card-info">
                <h4>Requêtes SQL</h4>
                <p>Total page</p>
            </div>
        </div>

        <div class="dnc-card" data-spark="php_memory">
            <div class="dnc-badge"><?php echo $runtime['php_memory']; ?> Mo</div>
            <div class="dnc-card-info">
                <h4>PHP Memory</h4>
                <p>Utilisée</p>
            </div>
        </div>

    </div>
    <?php
}
