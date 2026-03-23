// Element references
const map = document.getElementById('main-map');
const cloudLayer = document.getElementById('clouds-layer');
const mapInner = document.getElementById('map-inner');
const overlay = document.getElementById('world-overlay');

// === Region overlay ===
function showOverlay(regionKey) {
  const config = (typeof regionConfigs !== 'undefined') ? regionConfigs[regionKey] : null;
  if (!config) return;
  overlay.src = config.image;
  overlay.style.top = `${config.top}px`;
  overlay.style.left = `${config.left}px`;
  overlay.style.width = `${config.width}px`;
  overlay.style.height = 'auto';
  overlay.classList.remove('hidden');
}

// === Clouds ===
function createCloud(src) {
  // Ensure we have dimensions to work with
  const innerW = mapInner.offsetWidth || mapInner.clientWidth;
  const innerH = mapInner.offsetHeight || mapInner.clientHeight;
  if (!innerW || !innerH) {
    // Try again on the next frame if sizes aren't ready yet
    requestAnimationFrame(() => createCloud(src));
    return;
  }

  const cloud = document.createElement('img');
  cloud.src = src;
  cloud.classList.add('cloud');

  const size = Math.random() * 150 + 100;
  const maxTop = Math.max(innerH - size, 0);

  cloud.style.width = `${size}px`;
  cloud.style.top = `${Math.random() * maxTop}px`;
  cloud.style.left = `-${size}px`;
  cloud.style.opacity = (Math.random() * 0.3 + 0.2).toFixed(2);

  cloudLayer.appendChild(cloud);

  const distance = innerW + size * 2;
  gsap.to(cloud, {
    x: distance,
    duration: Math.random() * 40 + 40,
    ease: "linear",
    onComplete: () => {
      cloud.remove();
      createCloud(src);
    }
  });
}

function createClouds() {
  const sources = ['cloud_01.png', 'cloud_02.png', 'cloud_03.png'].map(f => `assets/overlays/${f}`);
  for (const src of sources) {
    for (let i = 0; i < 3; i++) createCloud(src);
  }
}

// Wait until the map image has real dimensions, then spawn clouds.
// Also handle late-loading via resize observer for safety.
function initCloudsWhenReady() {
  const start = () => createClouds();

  if (map && map.complete && map.naturalWidth > 0) {
    start();
  } else if (map) {
    map.addEventListener('load', start, { once: true });
  } else {
    // Fallback if #main-map is absent (defensive)
    requestAnimationFrame(start);
  }

  // In case layout changes later (e.g., responsive), clouds continue cycling with fresh spawns.
}

// === Draggable panel ===
function makeDraggable(panelId, handleId) {
  const panel = document.getElementById(panelId);
  const handle = document.getElementById(handleId);
  if (!panel || !handle) return;

  let offsetX = 0, offsetY = 0, isDragging = false;

  const drag = (e) => {
    if (!isDragging) return;
    panel.style.left = `${e.clientX - offsetX}px`;
    panel.style.top = `${e.clientY - offsetY}px`;
  };

  const stopDrag = () => {
    isDragging = false;
    document.removeEventListener('mousemove', drag);
    document.removeEventListener('mouseup', stopDrag);
  };

  handle.addEventListener('mousedown', (e) => {
    isDragging = true;
    // Use getBoundingClientRect for more consistent offsets
    const rect = panel.getBoundingClientRect();
    offsetX = e.clientX - rect.left;
    offsetY = e.clientY - rect.top;
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', stopDrag);
  });
}

// === Info panel toggle ===
function setupPanelToggle() {
  const button = document.getElementById('toggle-panel');
  const content = document.getElementById('info-content');
  if (!button || !content) return;

  const COLLAPSE_ICON = '-';
  const EXPAND_ICON = '+';

  const updateIcon = () => {
    const isHidden = content.style.display === 'none';
    button.textContent = isHidden ? COLLAPSE_ICON : EXPAND_ICON;
  };

  button.addEventListener('click', () => {
    const hidden = content.style.display === 'none';
    content.style.display = hidden ? 'block' : 'none';
    updateIcon();
  });

  // Initialize icon to match current state
  updateIcon();
}

// === Audio ===
let masterVolume = 0.10;
let isMuted = true; // start muted

function setupAudioControls() {
  const slider = document.getElementById('volume-slider');
  const muteBtn = document.getElementById('mute-btn');
  if (!slider || !muteBtn) return;

  const ICON_MUTED = 'Mute';
  const ICON_UNMUTED = 'Sound';

  const applyVolume = () => {
    Howler.volume(isMuted ? 0 : masterVolume);
  };

  slider.addEventListener('input', () => {
    masterVolume = parseFloat(slider.value);
    applyVolume();
  });

  muteBtn.addEventListener('click', () => {
    isMuted = !isMuted;
    Howler.mute(isMuted);
    applyVolume();
    muteBtn.textContent = isMuted ? ICON_MUTED : ICON_UNMUTED;
  });

  // Initial state (muted on load)
  Howler.mute(true);
  applyVolume();
  muteBtn.textContent = ICON_MUTED;
}

function playAudioTrack(src, { volume = 0.03, loop = true, fadeIn = 2000 } = {}) {
  const sound = new Howl({ src: [src], loop, volume });
  sound.volume(0);
  sound.play();
  sound.fade(0, volume, fadeIn);
  return sound;
}

function loadShantyPlaylist() {
  const shanties = Array.from({ length: 35 }, (_, i) => `assets/audio/shanties/shanty${i + 1}.mp3`);
  let lastShantyIndex = -1;

  function playNext() {
    let index;
    do {
      index = Math.floor(Math.random() * shanties.length);
    } while (index === lastShantyIndex && shanties.length > 1);

    lastShantyIndex = index;

    const sound = new Howl({
      src: [shanties[index]],
      volume: masterVolume
    });

    sound.play();
    sound.on('end', () => {
      const delay = Math.random() * 30 + 60; // 60–90s gap
      setTimeout(playNext, delay * 1000);
    });
  }

  const initialDelay = Math.random() * 30 + 60;
  setTimeout(playNext, initialDelay * 1000);
}

// === Init ===
window.addEventListener('load', () => {
  initCloudsWhenReady();
  setupAudioControls();
  playAudioTrack('assets/audio/waves_01.mp3', { volume: 0.05, loop: true, fadeIn: 2000 });
  loadShantyPlaylist();
  setupPanelToggle();
  // If present elsewhere, this will safely call; otherwise ignored
  if (typeof setupZoomControls === 'function') setupZoomControls();
});

