document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.getElementById("toggleSidebar");
    const sidebar = document.getElementById("sidebar");
    const mobileBtn = document.getElementById("sidebarMobileButton");
    const overlay = document.getElementById("sidebarOverlay");

    if (!sidebar) {
        return;
    }

    const sidebarGuardado = localStorage.getItem("sidebar_esam_collapsed");

    if (sidebarGuardado === "1") {
        sidebar.classList.add("collapsed");
    }

    if (toggleBtn) {
        toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("collapsed");

            localStorage.setItem(
                "sidebar_esam_collapsed",
                sidebar.classList.contains("collapsed") ? "1" : "0"
            );
        });
    }

    if (mobileBtn && overlay) {
        mobileBtn.addEventListener("click", function () {
            sidebar.classList.add("mobile-open");
            overlay.classList.add("active");
        });

        overlay.addEventListener("click", function () {
            sidebar.classList.remove("mobile-open");
            overlay.classList.remove("active");
        });
    }

    document.querySelectorAll(".sidebar-link").forEach(function (link) {
        link.addEventListener("click", function () {
            if (window.innerWidth <= 992 && overlay) {
                sidebar.classList.remove("mobile-open");
                overlay.classList.remove("active");
            }
        });
    });
});