const projects = [
  {
    title: "ARC",
    category: "game",
    tag: "Unity",
    description:
      "使用 Unity 製作的半寫實賽車遊戲，後續會加入不同控制器測試，探索駕駛手感與輸入互動。",
    stack: ["Unity", "Racing Game", "Controller Test", "Vercel"],
    url: "https://arc-web-mocha.vercel.app/",
  },
  {
    title: "Lone Escape: Descent into Darkness",
    category: "game",
    tag: "Phaser",
    description:
      "像素風格 2D 俯視角恐怖逃生遊戲，以策略性移動、生存挑戰與關卡判斷為核心。",
    stack: ["Phaser", "Pixel Art", "2D Horror", "Vercel"],
    url: "https://lone-escape-descent-into-darkness.vercel.app/",
  },
  {
    title: "Chronostasis",
    category: "interactive",
    tag: "Interactive",
    description:
      "藉由手勢操控時間流動，讓觀眾不只是觀看者，而是參與影像、時間與感知變化的人。",
    stack: ["TouchDesigner", "MediaPipe", "Gesture", "Interactive Art"],
    url: "https://youtu.be/JFRc-Lt4ZZA",
    ctaLabel: "影片展示",
  },
  {
    title: "二拾光 TTL",
    category: "web",
    tag: "Web",
    description:
      "讓舊物再度閃耀的網站，每件物品都保存一段故事，也等待下一位使用者延續它的價值。",
    stack: ["PHP", "SQL", "Web App", "Storytelling"],
    url: "https://ttl-woad.vercel.app/",
  },
  {
    title: "互動式活動網站",
    category: "web",
    tag: "Web",
    description:
      "為校園活動設計的響應式網站，包含活動資訊、報名入口與視覺化時程，降低找資訊的成本。",
    stack: ["HTML", "CSS", "JavaScript"],
  },
  {
    title: "資料視覺化儀表板",
    category: "visual",
    tag: "Visual",
    description:
      "把分散資料整理成圖表與摘要卡片，協助快速比較趨勢、狀態與異常項目。",
    stack: ["Charts", "Dashboard", "Data"],
  },
  {
    title: "品牌作品集改版",
    category: "web",
    tag: "Web",
    description:
      "重新規劃個人品牌網站的架構、作品呈現與聯絡動線，讓訪客快速理解能力與風格。",
    stack: ["Branding", "Responsive", "SEO"],
  },
];

const featuredTrack = document.querySelector("#featuredTrack");
const projectGrid = document.querySelector("#projectGrid");
const filterButtons = document.querySelectorAll(".filter-button");
const menuButton = document.querySelector(".menu-button");
const navLinks = document.querySelector(".nav-links");
const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

function projectMeta(project) {
  return project.stack.map((item) => `<span>${item}</span>`).join("");
}

function projectLink(project) {
  if (!project.url) {
    return "";
  }

  return `<span class="project-link" aria-hidden="true">${project.ctaLabel || "線上 Demo"}</span>`;
}

function renderFeaturedProjects() {
  featuredTrack.innerHTML = projects
    .slice(0, 5)
    .map((project, index) => {
      const slideTag = project.url ? "a" : "article";
      const ctaLabel = project.ctaLabel || "線上 Demo";
      const linkAttributes = project.url
        ? `href="${project.url}" aria-label="開啟 ${project.title} ${ctaLabel}"`
        : "";

      return `
        <${slideTag} class="project-slide ${project.url ? "project-slide-clickable" : ""}" ${linkAttributes}>
          <div class="project-slide__top">
            <span class="project-kicker">${project.tag}</span>
            <span class="project-number">${String(index + 1).padStart(2, "0")}</span>
          </div>
          <div>
            <h3>${project.title}</h3>
            <p>${project.description}</p>
          </div>
          <div class="project-meta">${projectMeta(project)}</div>
          ${projectLink(project)}
        </${slideTag}>
      `;
    })
    .join("");
}

function renderProjectGrid(activeFilter = "all") {
  projectGrid.innerHTML = projects
    .filter((project) => activeFilter === "all" || activeFilter === project.category)
    .map((project, index) => {
      const cardTag = project.url ? "a" : "article";
      const ctaLabel = project.ctaLabel || "線上 Demo";
      const linkAttributes = project.url
        ? `href="${project.url}" aria-label="開啟 ${project.title} ${ctaLabel}"`
        : "";

      return `
        <${cardTag} class="project-card ${project.url ? "project-card-clickable" : ""}" ${linkAttributes}>
          <div class="project-top">
            <span class="project-tag">${project.tag}</span>
            <span class="project-number">${String(index + 1).padStart(2, "0")}</span>
          </div>
          <div>
            <h3>${project.title}</h3>
            <p>${project.description}</p>
          </div>
          <div class="project-meta">${projectMeta(project)}</div>
          ${project.url ? `<span class="project-link" aria-hidden="true">${ctaLabel}</span>` : ""}
        </${cardTag}>
      `;
    })
    .join("");
}

function setupMenu() {
  menuButton.addEventListener("click", () => {
    const isOpen = navLinks.classList.toggle("open");
    document.body.classList.toggle("menu-open", isOpen);
    menuButton.setAttribute("aria-expanded", String(isOpen));
  });

  navLinks.addEventListener("click", (event) => {
    if (!event.target.matches("a")) {
      return;
    }

    navLinks.classList.remove("open");
    document.body.classList.remove("menu-open");
    menuButton.setAttribute("aria-expanded", "false");
  });
}

function setupFilters() {
  filterButtons.forEach((button) => {
    button.addEventListener("click", () => {
      filterButtons.forEach((item) => {
        item.classList.remove("active");
        item.setAttribute("aria-selected", "false");
      });

      button.classList.add("active");
      button.setAttribute("aria-selected", "true");
      renderProjectGrid(button.dataset.filter);
      animateProjectGrid();

      if (window.ScrollTrigger) {
        ScrollTrigger.refresh();
      }
    });
  });
}

function setupCustomCursor() {
  const cursor = document.querySelector(".cursor-ring");
  const canUseCustomCursor = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  if (!cursor || !canUseCustomCursor || prefersReducedMotion) {
    return;
  }

  let moveCursor = (x, y) => {
    cursor.style.left = `${x}px`;
    cursor.style.top = `${y}px`;
  };

  if (window.gsap) {
    const quickX = gsap.quickTo(cursor, "left", { duration: 0.18, ease: "power3.out" });
    const quickY = gsap.quickTo(cursor, "top", { duration: 0.18, ease: "power3.out" });
    moveCursor = (x, y) => {
      quickX(x);
      quickY(y);
    };
  }

  window.addEventListener("pointermove", (event) => {
    cursor.classList.add("cursor-visible");
    moveCursor(event.clientX, event.clientY);
  });

  window.addEventListener("pointerleave", () => {
    cursor.classList.remove("cursor-visible");
  });

  window.addEventListener("pointerdown", () => {
    cursor.classList.add("cursor-pressed");
  });

  window.addEventListener("pointerup", () => {
    cursor.classList.remove("cursor-pressed");
  });

  document.addEventListener("pointerover", (event) => {
    if (event.target.closest("a, button, .project-card-clickable, .project-slide-clickable, .filter-button")) {
      cursor.classList.add("cursor-hover");
    }
  });

  document.addEventListener("pointerout", (event) => {
    if (event.target.closest("a, button, .project-card-clickable, .project-slide-clickable, .filter-button")) {
      cursor.classList.remove("cursor-hover");
    }
  });
}

function animateProjectGrid() {
  if (!window.gsap || prefersReducedMotion) {
    return;
  }

  gsap.fromTo(
    ".project-card",
    { autoAlpha: 0, y: 34 },
    {
      autoAlpha: 1,
      y: 0,
      duration: 0.55,
      ease: "power2.out",
      stagger: 0.055,
      overwrite: true,
    },
  );
}

function setupAnimations() {
  if (!window.gsap || !window.ScrollTrigger || prefersReducedMotion) {
    document.querySelector(".scroll-progress__bar").style.transform = "scaleX(1)";
    return;
  }

  gsap.registerPlugin(ScrollTrigger);
  gsap.defaults({ ease: "power3.out", duration: 0.9 });

  gsap.to(".scroll-progress__bar", {
    scaleX: 1,
    ease: "none",
    scrollTrigger: {
      start: 0,
      end: "max",
      scrub: 0.2,
    },
  });

  gsap.from(".site-header", { autoAlpha: 0, y: -22, duration: 0.65 });
  gsap.from(".hero-copy > *", { autoAlpha: 0, y: 42, stagger: 0.1, duration: 0.85 });
  gsap.from(".hero-visual", { autoAlpha: 0, x: 52, rotate: 2.5, duration: 1 });
  gsap.from(".hero-visual figcaption", { autoAlpha: 0, y: 24, delay: 0.4, duration: 0.7 });

  gsap.to(".intro-strip__inner", {
    xPercent: -50,
    ease: "none",
    scrollTrigger: {
      trigger: ".intro-strip",
      start: "top bottom",
      end: "bottom top",
      scrub: 1,
    },
  });

  gsap.utils.toArray(".reveal").forEach((element) => {
    gsap.from(element, {
      autoAlpha: 0,
      y: 54,
      scrollTrigger: {
        trigger: element,
        start: "top 82%",
        toggleActions: "play none none reverse",
      },
    });
  });

  ScrollTrigger.batch(".reveal-card", {
    start: "top 82%",
    onEnter: (batch) =>
      gsap.fromTo(
        batch,
        { autoAlpha: 0, y: 38 },
        { autoAlpha: 1, y: 0, stagger: 0.09, duration: 0.7, overwrite: true },
      ),
    onLeaveBack: (batch) => gsap.set(batch, { autoAlpha: 0, y: 38, overwrite: true }),
  });

  gsap.set(".reveal-card", { autoAlpha: 0, y: 38 });

  gsap.utils.toArray("[data-count]").forEach((number) => {
    const value = Number(number.dataset.count);

    gsap.fromTo(
      number,
      { textContent: 0 },
      {
        textContent: value,
        duration: 1.35,
        ease: "power1.out",
        snap: { textContent: 1 },
        scrollTrigger: {
          trigger: number,
          start: "top 86%",
          once: true,
        },
        onUpdate() {
          number.textContent = String(Math.round(Number(number.textContent)));
        },
      },
    );
  });

  const mm = gsap.matchMedia();

  mm.add("(min-width: 780px)", () => {
    const track = document.querySelector(".project-track");
    const getScrollDistance = () => Math.max(1, track.scrollWidth - window.innerWidth);

    const horizontalTween = gsap.to(track, {
      x: () => -getScrollDistance(),
      ease: "none",
      scrollTrigger: {
        trigger: ".horizontal-showcase",
        start: "top top",
        end: () => `+=${getScrollDistance()}`,
        pin: true,
        scrub: 1,
        invalidateOnRefresh: true,
      },
    });

    gsap.utils.toArray(".project-slide").forEach((slide) => {
      gsap.from(slide, {
        scale: 0.94,
        autoAlpha: 0.55,
        scrollTrigger: {
          trigger: slide,
          containerAnimation: horizontalTween,
          start: "left 82%",
          end: "left 36%",
          scrub: 0.6,
        },
      });
    });

    return () => {
      horizontalTween.kill();
    };
  });
}

document.querySelector("#year").textContent = new Date().getFullYear();
renderFeaturedProjects();
renderProjectGrid();
setupMenu();
setupFilters();
setupCustomCursor();
setupAnimations();
animateProjectGrid();
