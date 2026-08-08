/* ============================================================
   alex.dev — landing page interactions
   ============================================================ */
(function () {
  "use strict";

  /* Enable JS-gated styles (reveal animations, etc.) */
  document.documentElement.classList.add("js");

  /* Prevent placeholder links (href="#") from jumping to top */
  document.querySelectorAll('a[href="#"]').forEach((link) => {
    link.addEventListener("click", (e) => e.preventDefault());
  });

  /* ---------- Footer year ---------- */
  document.getElementById("year").textContent = new Date().getFullYear();

  /* ---------- Nav: scroll state ---------- */
  const nav = document.getElementById("nav");
  const toTop = document.getElementById("toTop");
  const onScroll = () => {
    const y = window.scrollY;
    nav.classList.toggle("is-scrolled", y > 12);
    toTop.classList.toggle("is-visible", y > 600);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile menu ---------- */
  const toggle = document.getElementById("navToggle");
  const closeMenu = () => {
    nav.classList.remove("is-open");
    toggle.setAttribute("aria-expanded", "false");
  };
  toggle.addEventListener("click", () => {
    const open = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", String(open));
  });
  nav.querySelectorAll(".nav__link").forEach((link) =>
    link.addEventListener("click", closeMenu)
  );
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeMenu();
  });

  /* ---------- Active nav link on scroll ---------- */
  const sections = [...document.querySelectorAll("main section[id]")];
  const navLinks = [...document.querySelectorAll(".nav__link")];
  const linkFor = (id) => navLinks.find((l) => l.getAttribute("href") === "#" + id);

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          navLinks.forEach((l) => l.classList.remove("is-active"));
          const link = linkFor(entry.target.id);
          if (link) link.classList.add("is-active");
        }
      });
    },
    { rootMargin: "-45% 0px -50% 0px" }
  );
  sections.forEach((s) => observer.observe(s));

  /* ---------- Scroll reveal ---------- */
  const revealEls = document.querySelectorAll(".reveal");
  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.12 }
  );
  revealEls.forEach((el, i) => {
    el.setAttribute("data-delay", String(i % 4));
    revealObserver.observe(el);
  });

  /* ---------- Count-up stats ---------- */
  const counters = document.querySelectorAll(".stat-num");
  const countObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const target = +el.dataset.count;
        const duration = 1200;
        const start = performance.now();
        const tick = (now) => {
          const p = Math.min((now - start) / duration, 1);
          const eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * eased);
          if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        countObserver.unobserve(el);
      });
    },
    { threshold: 0.6 }
  );
  counters.forEach((c) => countObserver.observe(c));

  /* ---------- Typing effect ---------- */
  const roles = [
    "LLM-powered applications",
    "RAG & AI agents",
    "Computer vision systems",
    "MLOps & production ML",
    "Data pipelines & forecasting",
  ];
  const typingEl = document.getElementById("typing");
  let roleIdx = 0, charIdx = 0, deleting = false;

  function typeLoop() {
    const word = roles[roleIdx];
    if (!deleting) {
      typingEl.textContent = word.slice(0, ++charIdx);
      if (charIdx === word.length) {
        deleting = true;
        setTimeout(typeLoop, 1800);
        return;
      }
      setTimeout(typeLoop, 55);
    } else {
      typingEl.textContent = word.slice(0, --charIdx);
      if (charIdx === 0) {
        deleting = false;
        roleIdx = (roleIdx + 1) % roles.length;
        setTimeout(typeLoop, 350);
        return;
      }
      setTimeout(typeLoop, 30);
    }
  }
  setTimeout(typeLoop, 600);

  /* ---------- Back to top ---------- */
  toTop.addEventListener("click", () =>
    window.scrollTo({ top: 0, behavior: "smooth" })
  );

  /* ---------- Contact form ---------- */
  const form = document.getElementById("contactForm");
  const toast = document.getElementById("toast");
  let toastTimer;

  function showToast(message, isError) {
    toast.textContent = message;
    toast.classList.toggle("is-error", Boolean(isError));
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 4200);
  }

  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  function setError(field, message) {
    const name = field.name;
    const errorEl = form.querySelector('[data-error-for="' + name + '"]');
    if (errorEl) errorEl.textContent = message;
    field.classList.toggle("is-invalid", Boolean(message));
    return !message;
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const name = form.elements["name"];
    const email = form.elements["email"];
    const message = form.elements["message"];

    let ok = true;
    ok = setError(name, name.value.trim().length >= 2 ? "" : "Please tell me your name (min. 2 characters).") && ok;
    ok = setError(email, emailRe.test(email.value.trim()) ? "" : "Please enter a valid email address.") && ok;
    ok = setError(message, message.value.trim().length >= 10 ? "" : "Please add a few details about your project (min. 10 characters).") && ok;

    if (!ok) {
      showToast("A couple of fields need attention — see the notes above.", true);
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "Sending…";

    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = original;
      form.reset();
      [name, email, message].forEach((f) => f.classList.remove("is-invalid"));
      showToast("Thanks! Your message is on its way — I'll reply within 24 hours. ✨");
    }, 1200);
  });

  /* Clear errors as the user types */
  ["name", "email", "message"].forEach((fieldName) => {
    const field = form.elements[fieldName];
    if (!field) return;
    field.addEventListener("input", () => {
      if (field.classList.contains("is-invalid")) {
        setError(field, "");
      }
    });
  });
})();
