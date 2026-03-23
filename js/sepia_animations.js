document.addEventListener("DOMContentLoaded", () => {
  const container = document.querySelector(".sepia-banner-container");
  const staticImg = container?.querySelector("#static-frame-info");
  const video = container?.querySelector("#animated-frame-info");

  if (!container || !staticImg || !video) return;

  let fadeBackTriggered = false;

  function fadeToVideo() {
    staticImg.style.opacity = "0";
    video.style.opacity = "1";
    video.play();
  }

  function fadeToSepia() {
    if (fadeBackTriggered) return;
    fadeBackTriggered = true;

    video.style.opacity = "0";
    staticImg.style.opacity = "1";

    setTimeout(() => {
      video.pause();
      fadeBackTriggered = false;
    }, 1500);
  }

  video.addEventListener("canplay", fadeToVideo);

  video.addEventListener("timeupdate", () => {
    if (video.duration && video.currentTime >= video.duration - 1.5) {
      fadeToSepia();
    }
  });

  container.addEventListener("click", () => {
    video.currentTime = 0;
    video.play();
    fadeToVideo();
  });

  staticImg.style.opacity = "1";
  video.style.opacity = "0";
});