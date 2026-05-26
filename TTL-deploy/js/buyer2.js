document.addEventListener('DOMContentLoaded', function () {
  // ✅ 取得使用者資料與頭像
  fetch('php/get_user_info.php')
    .then(res => res.json())
    .then(data => {
      document.getElementById('username').textContent = data.name;
      document.getElementById('avatarImg').src = 'Image/uploads/' + data.avatar + '?t=' + Date.now();
      document.getElementById('userPoints').textContent = data.points;
    });

  // ✅ 處理頭像上傳
  document.getElementById('avatarUpload').addEventListener('change', function () {
    const formData = new FormData();
    formData.append('avatar', this.files[0]);

    fetch('php/upload_avatar.php', {
      method: 'POST',
      body: formData
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('avatarImg').src = data.path + '?t=' + Date.now();
        } else {
          alert('上傳失敗：' + data.message);
        }
      });
  });

  // ✅ 載入歷史訂單資料
  fetch("php/get_orders_items.php")
    .then(res => res.json())
    .then(data => {
      const container = document.getElementById('cartItemContainer');
      container.innerHTML = "";

      if (!data.success) {
        container.innerHTML = `<p class="text-danger">${data.message}</p>`;
        return;
      }

      const orders = data.orders;
      if (!orders || Object.keys(orders).length === 0) {
        container.innerHTML = `<p class="text-muted">尚無歷史交易紀錄。</p>`;
        return;
      }

      Object.entries(orders).forEach(([orderId, order]) => {
        const orderDiv = document.createElement('div');
        orderDiv.className = 'mb-4 p-3 border rounded bg-light';

        const header = document.createElement('h6');
        header.className = 'fw-bold mb-3';

        let headerHTML = `📦 訂單編號：${orderId} ｜ 下單時間：${order.created_at}`;
        if (order.complained === 1 || order.complained === "1") {
          headerHTML += `
            ｜ <span class="text-danger">【申訴中】</span>
            <button class="btn btn-sm btn-outline-danger ms-2 cancel-btn" data-order-id="${orderId}">
              取消申訴
            </button>
          `;
        }
        header.innerHTML = headerHTML;
        orderDiv.appendChild(header);

        // 商品清單
        order.items.forEach(item => {
          const product = document.createElement('div');
          product.className = 'product-item mb-2';
          product.innerHTML = `
            <div class="d-flex align-items-center">
              <img src="${item.image}" alt="商品圖片" style="width: 80px; height: 80px;">
              <div class="ms-3">
                <h6>${item.name}</h6>
                <p class="mb-0">數量：${item.quantity}　單價：$${item.price}</p>
              </div>
            </div>
          `;
          orderDiv.appendChild(product);
        });

        const total = document.createElement('p');
        total.className = 'fw-bold mt-2 text-dark';
        total.innerHTML = `
          折抵後總金額：$${order.total_amount}<br>
          使用點數：<span class="text-danger">${order.points_used || 0}</span> 點
        `;
        orderDiv.appendChild(total);

        container.appendChild(orderDiv);
      });

      // ✅ 綁定「取消申訴」按鈕
      setTimeout(() => {
        document.querySelectorAll(".cancel-btn").forEach(btn => {
          btn.addEventListener("click", function () {
            const orderId = this.getAttribute("data-order-id");
            if (!confirm(`確定要取消訂單 ${orderId} 的申訴嗎？`)) return;

            fetch("php/cancel_complaint.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({ orderId: orderId })
            })
              .then(res => res.json())
              .then(result => {
                if (result.success) {
                  alert("已取消申訴！");
                  location.reload();
                } else {
                  alert("取消失敗：" + result.message);
                }
              });
          });
        });
      }, 0);
    })
    .catch(err => {
      console.error("❌ 載入訂單失敗", err);
      document.getElementById('cartItemContainer').innerHTML = `<p class="text-danger">載入失敗</p>`;
    });
});
