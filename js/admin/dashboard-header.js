/**
 * Dashboard Header Pro – JS
 * - Animation des jauges
 * - Couleurs dynamiques
 * - Sparklines SVG with localStorage historique
 */

document.addEventListener("DOMContentLoaded", function () {

    /* ----------------------------------------------------------
       1. ANIMATION DES GAUGES
    ---------------------------------------------------------- */
    document.querySelectorAll(".dnc-gauge").forEach(g => {
        let val = parseFloat(g.dataset.value || 0);
        val = Math.min(Math.max(val, 0), 100);

        g.style.setProperty("--value", val + "%");

        // Couleur dynamique
        let color = "#22c55e"; // vert
        if (val > 70) color = "#f59e0b"; // orange
        if (val > 90) color = "#ef4444"; // rouge

        g.style.setProperty("--dnc-accent", color);
    });


    /* ----------------------------------------------------------
       2. SPARKLINES (mini-graphes)
       Stockés dans localStorage pour afficher historique
    ---------------------------------------------------------- */
    const metrics = {
        load_time: {
            key: "dnc_spark_load_time",
            value: parseFloat(document.querySelector(".dnc-card:nth-last-child(3) .dnc-badge")?.innerText || 0),
            maxPoints: 40
        },
        sql_queries: {
            key: "dnc_spark_sql",
            value: parseFloat(document.querySelector(".dnc-card:nth-last-child(2) .dnc-badge")?.innerText || 0),
            maxPoints: 40
        },
        php_memory: {
            key: "dnc_spark_memory",
            value: parseFloat(document.querySelector(".dnc-card:nth-last-child(1) .dnc-badge")?.innerText || 0),
            maxPoints: 40
        }
    };

    Object.keys(metrics).forEach(type => {

        let stored = JSON.parse(localStorage.getItem(metrics[type].key) || "[]");
        stored.push(metrics[type].value);

        // On limite le nombre de points
        while (stored.length > metrics[type].maxPoints) {
            stored.shift();
        }

        localStorage.setItem(metrics[type].key, JSON.stringify(stored));

        // Génération du sparkline
        const svg = dnc_generateSparkline(stored, 80, 28);

        // Injection si on a un container
        const parent = document.querySelector(`.dnc-card[data-spark="${type}"]`);
        if (parent) {
            parent.insertAdjacentHTML("beforeend", svg);
        }

    });

});


/* ----------------------------------------------------------
   3. Fonction sparkline SVG
---------------------------------------------------------- */
function dnc_generateSparkline(data, width, height) {
    if (!data || data.length < 2) return "";

    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;

    const step = width / (data.length - 1);

    let points = data.map((val, i) => {
        const x = i * step;
        const y = height - ((val - min) / range * height);
        return `${x},${y}`;
    }).join(" ");

    return `
        <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" class="dnc-sparkline">
            <polyline fill="none" stroke="#3b82f6" stroke-width="2" points="${points}" />
        </svg>
    `;
}
