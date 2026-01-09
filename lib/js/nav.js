/* =========================================================
   /public/lib/js/nav.js
   Mobile nav toggle for .nav + .nav-toggle
   Requires:
   - button.nav-toggle (aria-controls="<nav id>")
   - nav.nav#<id>
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  const navContainer = document.getElementById("nav");

  if (typeof subjects !== "undefined" && navContainer) {
    Object.values(subjects).forEach(subject => {
      const link = document.createElement("a");
      link.href = `/subjects/${subject.slug}/`;
      link.textContent = subject.name;
      link.classList.add("nav-link"); // optional styling hook
      navContainer.appendChild(link);
    });
  } else {
    console.warn("Subjects registry not found or nav container missing.");
  }
});

(function () {
  function initNavToggle() {
    const btn = document.querySelector(".nav-toggle");
    if (!btn) return;

    const id = btn.getAttribute("aria-controls");
    if (!id) return;

    const nav = document.getElementById(id);
    if (!nav) return;

    function setOpen(isOpen) {
      nav.classList.toggle("is-open", isOpen);
      btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    }

    btn.addEventListener("click", function () {
      const isOpen = nav.classList.contains("is-open");
      setOpen(!isOpen);
    });

    nav.addEventListener("click", function (e) {
      const a = e.target.closest("a");
      if (!a) return;
      setOpen(false);
    });

    window.addEventListener("resize", function () {
      if (window.matchMedia("(min-width: 821px)").matches) setOpen(false);
    });

    setOpen(false);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNavToggle);
  } else {
    initNavToggle();
  }
})();
