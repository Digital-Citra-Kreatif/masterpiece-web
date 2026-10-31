// Hitung mundur maintenance: Hari : Jam : Menit : Detik
const root = document.querySelector('[data-countdown]');
// Sisa detik dari server: jam perangkat yang salah tidak berpengaruh
const target = Date.now() + Number(root.dataset.countdown) * 1000;
const parts = Object.fromEntries([...root.querySelectorAll('[data-countdown-part]')].map((el) => [el.dataset.countdownPart, el]));
const pad = (n) => String(n).padStart(2, '0');

function tick() {
  const left = Math.max(0, Math.ceil((target - Date.now()) / 1000));
  parts.days.textContent = pad(Math.floor(left / 86400));
  parts.hours.textContent = pad(Math.floor((left % 86400) / 3600));
  parts.minutes.textContent = pad(Math.floor((left % 3600) / 60));
  parts.seconds.textContent = pad(left % 60);
  // Waktunya tiba: muat ulang, situs mungkin sudah dibuka
  if (left === 0) {
    setTimeout(() => location.reload(), 1000);
    return;
  }
  setTimeout(tick, ((target - Date.now()) % 1000) || 1000); // Pas di pergantian detik
}

if (!Number.isNaN(target)) tick();
