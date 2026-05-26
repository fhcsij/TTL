document.addEventListener('DOMContentLoaded', function () {
  fetch('Sample/navbar.html')
    .then(res => res.text())
    .then(data => {
      document.getElementById('navbar-placeholder').innerHTML = data;

      const currentPage = location.pathname.split("/").pop();

      // 設定導覽列 active 樣式
      document.querySelectorAll('.nav-link').forEach(link => {
        const linkPage = new URL(link.href).pathname.split("/").pop();
        if (currentPage === linkPage) {
          link.classList.add('active');
        }
      });

      const userIcon = document.getElementById('userIcon');
      const userDropdown = document.getElementById('userDropdown');
      const dropdownMenu = document.querySelector('.dropdown-menu');

      if (userIcon && userDropdown) {
        const currentPage = location.pathname.split("/").pop();
        const pagesWithUser2 = ['buyer.html', 'buyer2.html', 'seller.html', 'seller2.html'];
        let isHovering = false;

        // 初始設定
        if (pagesWithUser2.includes(currentPage)) {
          userIcon.src = 'Image/user2.png';
        }

        // 滑鼠移入 userIcon 或下拉區塊時 → 換成 user2.png
        [userDropdown, dropdownMenu].forEach(el => {
          if (el) {
            el.addEventListener('mouseenter', () => {
              userIcon.src = 'Image/user2.png';
              isHovering = true;
            });

            el.addEventListener('mouseleave', () => {
              isHovering = false;
              // 如果不是特定頁面才會還原
              setTimeout(() => {
                if (!isHovering && !pagesWithUser2.includes(currentPage)) {
                  userIcon.src = 'Image/user.png';
                }
              }, 100);
            });
          }
        });

        // 點擊空白處 → 還原 user.png
        document.addEventListener('click', function (e) {
          const isClickInside =
            userDropdown.contains(e.target) ||
            (dropdownMenu && dropdownMenu.contains(e.target));

          if (!isClickInside && !pagesWithUser2.includes(currentPage)) {
            userIcon.src = 'Image/user.png';
          }
        });
      }

      const cartIcon = document.getElementById('cartIcon');
      if (cartIcon) {
        const currentPage = location.pathname.split("/").pop();
        const hoverSrc = 'Image/cart2.png';
        const defaultSrc = 'Image/cart.png';

        // 預設在 cart.html 就是 cart2.png
        if (currentPage === 'cart.html') {
          cartIcon.src = hoverSrc;
        }

        // 滑鼠移入時切換為 cart2.png
        cartIcon.addEventListener('mouseenter', () => {
          cartIcon.src = hoverSrc;
        });

        // 滑鼠移出後還原（除非是在 cart.html）
        cartIcon.addEventListener('mouseleave', () => {
          if (currentPage !== 'cart.html') {
            cartIcon.src = defaultSrc;
          }
        });

        // 點擊後永久變成 cart2.png（可視需求加上 toggle 回來）
        cartIcon.addEventListener('click', () => {
          cartIcon.src = hoverSrc;
        });
      }

    });

  fetch('Sample/footer.html')
    .then(res => res.text())
    .then(data => {
      document.getElementById('footer-placeholder').innerHTML = data;
    });
});
