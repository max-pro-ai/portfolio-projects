document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('rbd-container');
  if (!container) return;

  fetch(RBD_DATA.endpoint)
    .then(resp => {
      if (!resp.ok) throw new Error('Network response was not ok');
      return resp.json();
    })
    .then(data => {
      if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML = '<div class="rbd-error">Бренды не найдены</div>';
        return;
      }

      let lastBrand = localStorage.getItem('rbd_last_brand'); 
      let item = null;

      // Пробуем выбрать новый бренд, отличный от предыдущего
      for (let i = 0; i < 5; i++) {
        let candidate = data[Math.floor(Math.random() * data.length)];
        if (!lastBrand || candidate.logo_file !== lastBrand) {
          item = candidate;
          break;
        }
      }

      // Если вдруг все попытки не дали нового — берём любой
      if (!item) {
        item = data[Math.floor(Math.random() * data.length)];
      }

      // Запоминаем для следующего раза
      localStorage.setItem('rbd_last_brand', item.logo_file);

      container.innerHTML = buildCard(item);

      // Плавная анимация
      const card = container.querySelector('.rbd-card');
      requestAnimationFrame(() => {
        card.style.opacity = '1';
        card.style.transform = 'scale(1)';
      });
    })
    .catch(err => {
      container.innerHTML = '<div class="rbd-error">Ошибка загрузки брендов</div>';
      console.error('RBD error:', err);
    });

  function buildCard(item) {
    const esc = (s) => {
      if (!s) return '';
      return s.replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
      });
    };

    return `
      <a class="rbd-card" href="${esc(item.link)}" target="_blank" rel="noopener noreferrer">
        <div class="rbd-logo-wrap">
          <img class="rbd-logo" src="${esc(item.logo)}" alt="${esc(item.logo_file)}" loading="lazy" />
        </div>
        <div class="rbd-desc">${esc(item.desc)}</div>
        <div class="rbd-link">Перейти на сайт →</div>
      </a>
    `;
  }
});
