document.addEventListener("DOMContentLoaded", function () {
  let userPoints = 0;
  let total = 0;

  const availablePointsEl = document.getElementById("availablePoints");
  const pointsInput = document.getElementById("pointsToUse");
  const finalAmountEl = document.getElementById("finalAmount");

  // 先取得使用者點數
  fetch("php/get_user_info.php")
    .then(res => res.json())
    .then(user => {
      if (user.success) {
        userPoints = user.points || 0;
        availablePointsEl.textContent = userPoints;
        pointsInput.max = userPoints;
      }
    });

  // 取得購物車內容
  fetch("php/get_cart_items.php")
    .then(res => res.json())
    .then(data => {
      const cartItems = document.getElementById("cartItems");
      const subtotalEl = document.getElementById("subtotal");
      const checkoutBtn = document.getElementById("checkoutBtn");
      const checkoutSection = document.getElementById("checkoutSection");
      const checkoutItems = document.getElementById("checkoutItems");
      const checkoutTotal = document.getElementById("checkoutTotal");

      cartItems.innerHTML = "";

      if (data.success && data.items.length > 0) {
        let checkoutHTML = "";
        total = 0;

        data.items.forEach(item => {
          total += Number(item.price);

          const div = document.createElement("div");
          div.className = "cart-item d-flex align-items-center";
          div.innerHTML = `
            <img src="${item.image}" alt="商品圖片">
            <div class="ms-3 flex-grow-1">
              <h6 class="fw-bold mb-1">${item.name}</h6>
              <p class="mb-1 text-muted">單價：$${Math.floor(item.price)}</p>
            </div>
            <div class="text-end">
              <p class="fw-bold mb-1">$${Math.floor(item.price)}</p>
              <button class="btn btn-sm btn-danger remove-btn" data-id="${item.product_id}">移除</button>
            </div>
          `;
          cartItems.appendChild(div);

          checkoutHTML += `<p>${item.name} - $${item.price}</p>`;
        });

        subtotalEl.textContent = `小計：$${total}`;
        checkoutTotal.textContent = total;
        checkoutItems.innerHTML = checkoutHTML;
        finalAmountEl.textContent = total;

        // 移除按鈕事件
        document.querySelectorAll(".remove-btn").forEach(btn => {
          btn.addEventListener("click", function () {
            const productId = this.dataset.id;
            fetch("php/remove_from_cart.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded"
              },
              body: `product_id=${encodeURIComponent(productId)}`
            })
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  alert("已從購物車移除");
                  location.reload();
                } else {
                  alert("移除失敗：" + data.message);
                }
              });
          });
        });

        // 顯示結帳區塊
        checkoutBtn.addEventListener("click", function () {
          checkoutSection.style.display = "block";

          // 自動滾動到結帳區塊
          setTimeout(() => {
            const offset = 90; // 上面預留空間 px
            const top = checkoutSection.getBoundingClientRect().top + window.scrollY - offset;

            window.scrollTo({
              top: top,
              behavior: "smooth"
            });
          }, 50);
        });

        // ✅ 即時更新折抵後金額
        pointsInput.addEventListener("input", function () {
          let inputPoints = parseInt(this.value) || 0;
          inputPoints = Math.max(0, Math.min(inputPoints, userPoints, total));
          this.value = inputPoints;
          finalAmountEl.textContent = total - inputPoints;
        });

        // ✅ 確認送出訂單
        document.getElementById("confirmOrderBtn").addEventListener("click", function () {
          const cardInput = document.getElementById("cardNumber");
          const cardNumber = cardInput.value.replace(/\s/g, '').trim();
          const pointsUsed = parseInt(pointsInput.value) || 0;
          const finalTotal = Math.max(0, total - pointsUsed);

          if (!/^\d{16}$/.test(cardNumber)) {
            alert("請輸入正確的 16 位數信用卡號碼");
            cardInput.focus();
            return;
          }

          fetch("php/create_order.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `card=${cardNumber}&points_used=${pointsUsed}&total=${finalTotal}`
          })
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
        cartItems.innerHTML = "<p class='text-muted'>購物車目前是空的。</p>";
        subtotalEl.textContent = "小計：$0";
        checkoutBtn.style.display = "none";
      }
    });

  document.getElementById("clearCartBtn").addEventListener("click", function () {
    if (confirm("確定要清空整個購物車嗎？")) {
      fetch("php/clear_cart.php", {
        method: "POST"
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert("購物車已清空");
            location.reload();
          } else {
            alert("清除失敗：" + data.message);
          }
        });
    }
  });

  // 輸入卡號時，每4位自動加空格
  const cardInput = document.getElementById("cardNumber");
  cardInput.addEventListener("input", function () {
    let value = this.value.replace(/\D/g, "").substring(0, 16);
    this.value = value.replace(/(.{4})/g, "$1 ").trim();
  });
});
