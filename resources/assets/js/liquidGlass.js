// Liquid Glass：镜面高光跟随指针
// 只写 CSS 变量，由样式表消费；触屏设备直接跳过。
const HOVER_NONE = window.matchMedia("(hover: none)");

let raf = 0;
let pending = null;

function flush() {
  raf = 0;
  if (!pending) return;
  const { el, rect, x, y } = pending;
  el.style.setProperty("--lg-mx", `${Math.round((x / rect.width) * 100)}%`);
  el.style.setProperty("--lg-my", `${Math.round((y / rect.height) * 100)}%`);
  pending = null;
}

document.addEventListener(
  "pointermove",
  (event) => {
    if (HOVER_NONE.matches) return;
    const target = event.target;
    if (!target || !target.closest) return;

    const el = target.closest(".item");
    if (!el) return;

    const rect = el.getBoundingClientRect();
    pending = {
      el,
      rect,
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };
    if (!raf) raf = requestAnimationFrame(flush);
  },
  { passive: true }
);

// ---------------------------------------------------------------
// 自动背景：TMDB 热门海报/剧照，按间隔轮换
// ---------------------------------------------------------------
const BG_INTERVAL = 10 * 60 * 1000;
const BG_DARK = "linear-gradient(180deg, rgba(6,9,14,.62) 0%, rgba(6,9,14,.46) 46%, rgba(6,9,14,.72) 100%)";
const BG_LIGHT = "linear-gradient(180deg, rgba(248,251,255,.74) 0%, rgba(238,243,250,.52) 48%, rgba(232,238,247,.68) 100%)";

function applyBackground(url) {
  const app = document.getElementById("app");
  if (!app || !url) return;

  const veil = window.matchMedia("(prefers-color-scheme: dark)").matches ? BG_DARK : BG_LIGHT;
  app.style.backgroundImage = `${veil}, url("${url}")`;
  app.style.backgroundSize = "cover";
  app.style.backgroundPosition = "center";
  app.style.backgroundRepeat = "no-repeat";
  app.style.backgroundAttachment = "fixed";
}

async function refreshBackground() {
  try {
    const res = await fetch("/api/background", { headers: { Accept: "application/json" } });
    if (!res.ok) return;
    const data = await res.json();
    if (data && data.url) applyBackground(data.url);
  } catch (err) {
    /* 背景拿不到不影响使用 */
  }
}

refreshBackground();
window.setInterval(refreshBackground, BG_INTERVAL);

// ---------------------------------------------------------------
// 设置页：分组 tab 切换
// 用事件委托绑在 document 上，不依赖初始化时机，
// 也不会因为脚本早于面板渲染而失效。
// ---------------------------------------------------------------
document.addEventListener("click", (event) => {
  const target = event.target;
  if (!target || !target.closest) return;

  const tab = target.closest(".settings-tab");
  if (!tab) return;

  const id = tab.dataset.tab;
  if (!id) return;

  event.preventDefault();

  document.querySelectorAll(".settings-tab").forEach((item) => {
    const on = item === tab;
    item.classList.toggle("active", on);
    item.setAttribute("aria-selected", on ? "true" : "false");
  });

  document.querySelectorAll(".settings-panel").forEach((panel) => {
    panel.classList.toggle("active", panel.id === id);
  });

  if (history.replaceState) {
    history.replaceState(null, "", "#" + id);
  }
});

// 进入 tab 模式：只有在脚本确实跑起来、且页面上有 tab 时才隐藏非活动分组。
// 万一脚本没加载，样式表保持「全部分组堆叠」，设置项不会消失。
function enableSettingsTabs() {
  if (!document.body) return;
  if (!document.querySelector(".settings-tab")) return;

  document.body.classList.add("lg-tabs-ready");

  const hash = window.location.hash;
  if (!hash || !hash.startsWith("#group-")) return;

  const tab = document.querySelector('.settings-tab[data-tab="' + hash.slice(1) + '"]');
  if (tab) tab.click();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", enableSettingsTabs);
} else {
  enableSettingsTabs();
}
