document.addEventListener("DOMContentLoaded", function () {
   // ✅ 取得使用者資料與頭像
  fetch('php/get_user_info.php')
    .then(res => res.json())
    .then(data => {
      document.getElementById('username').textContent = data.name;
      document.getElementById('avatarImg').src = 'Image/uploads/' + data.avatar + '?t=' + Date.now();
      document.getElementById('userPoints').textContent = data.points;
    });

  // ✅ 處理頭像上傳
  document.getElementById('avatarUpload').addEventListener('change', function (e) {
    const formData = new FormData();
    formData.append('avatar', e.currentTarget.files[0]);

    fetch('php/upload_avatar.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        console.log(data)
        if (data.success) {
          document.getElementById('avatarImg').src = data.path + '?t=' + Date.now();
        } else {
          alert('上傳失敗12ddw3：' + data.message);
        }
      });
  });

  // ✅ 載入購物車商品
  fetch("php/get_cart_items.php")
    .then(res => res.json())
    .then(data => {
      const cartItems = document.getElementById("cartItemContainer");
      const subtotalEl = document.getElementById("subtotal");
      const checkoutBtn = document.getElementById("checkoutBtn");
      const checkoutSection = document.getElementById("checkoutSection");
      const checkoutItems = document.getElementById("checkoutItems");
      const checkoutTotal = document.getElementById("checkoutTotal");

      cartItems.innerHTML = "";

      if (data.success && data.items.length > 0) {
        let total = 0;
        let checkoutHTML = "";

        data.items.forEach(item => {
          total += Number(item.price);

          const div = document.createElement("div");
          div.className = "product-item";
          div.innerHTML = `
            <div class="d-flex align-items-center">
              <img src="${item.image}" alt="商品圖片" style="width: 80px; height: 80px;">
              <span class="ms-3 me-3">${item.name}</span>
              <span class="ms-auto">$${item.price}</span>
            </div>
          `;
          cartItems.appendChild(div);

          checkoutHTML += `<p>${item.name} - $${item.price}</p>`;
        });

        subtotalEl.textContent = `小計：$${total}`;
        checkoutTotal.textContent = total;
        checkoutItems.innerHTML = checkoutHTML;

        // 顯示結帳按鈕區
        checkoutBtn.style.display = "block";

        // 顯示結帳區塊
        checkoutBtn.addEventListener("click", function () {
          checkoutSection.style.display = "block";
        });

        // 處理結帳送出
        document.getElementById("confirmOrderBtn").addEventListener("click", function () {
          const cardInput = document.getElementById("cardNumber");
          const cardNumber = cardInput.value.trim();

          if (!/^\d{16}$/.test(cardNumber)) {
            alert("請輸入正確的 16 位數信用卡號碼");
            cardInput.focus();
            return;
          }

          fetch("php/create_order.php", { method: "POST" })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                alert("訂單已送出！");
                location.reload();
              } else {
                alert("訂單失敗：" + data.message);
              }
            });
        });
      } else {
        cartItems.innerHTML = "<p class='text-muted'>目前沒有待結帳商品。</p>";
        subtotalEl.textContent = "小計：$0";
        checkoutBtn.style.display = "none";
      }
    })
    .catch(err => {
      console.error("❌ 載入購物車錯誤", err);
    });
});