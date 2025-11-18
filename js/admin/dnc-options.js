document.addEventListener("DOMContentLoaded", function() {

    /* ============================================================
       TABS
    ============================================================ */
    const tabs = document.querySelectorAll(".dnc-tab");
    const contents = document.querySelectorAll(".dnc-tab-content");

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {

            tabs.forEach(t => t.classList.remove("active"));
            contents.forEach(c => c.classList.remove("active"));

            tab.classList.add("active");

            const target = document.getElementById("dnc-tab-" + tab.dataset.tab);
            if (target) target.classList.add("active");
        });
    });


    /* ============================================================
       ACCORDÉONS
    ============================================================ */
    const accBtns = document.querySelectorAll(".dnc-accordion-btn");

    accBtns.forEach(btn => {
        btn.addEventListener("click", () => {

            const content = btn.nextElementSibling;

            btn.classList.toggle("active");
            content.classList.toggle("active");

        });
    });


    /* ============================================================
       SMOOTH SAVE ANIMATION
    ============================================================ */
    const saveBtn = document.querySelector(".dnc-save-btn");
    if (saveBtn) {
        saveBtn.addEventListener("click", () => {
            saveBtn.classList.add("saving");
            setTimeout(() => saveBtn.classList.remove("saving"), 1500);
        });
    }

});
