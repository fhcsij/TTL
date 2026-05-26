const complaintBtn = document.getElementById("complaintBtn");
const complaintModal = document.getElementById("complaintModal");
const closeModal = document.getElementById("closeModal");
const complaintForm = document.getElementById("complaintForm");
const orderList = document.getElementById("orderList");

let selectedOrderId = null; // 使用者選擇的訂單編號

// 開啟彈窗時載入訂單
complaintBtn.addEventListener("click", () => {
  complaintModal.style.display = "flex";
  selectedOrderId = null; // 清除上次選擇

  fetch("php/get_order_history.php")
    .then(res => res.json())
    .then(data => {
      orderList.innerHTML = ""; // 清空列表

      data.orders.forEach(order => {
        const item = document.createElement("div");
        item.className = "list-group-item list-group-item-action";
        item.style.cursor = "pointer";

        if (order.complained == 1) {
          // 已申訴的訂單樣式
          item.textContent = `訂單編號：${order.id} ｜ 總金額：$${order.total_price} ｜ 此單使用點數：${order.points_used} ｜【申訴中】`;
          item.classList.add("disabled", "text-muted");
          item.style.pointerEvents = "none";
        } else {
          // 可點選的訂單
          item.textContent = `訂單編號：${order.id} ｜ 總金額：$${order.total_price} ｜ 此單使用點數：${order.points_used}`;
          item.addEventListener("click", () => {
            document.querySelectorAll("#orderList .list-group-item").forEach(i => {
              i.classList.remove("active");
            });
            item.classList.add("active");
            selectedOrderId = order.id;
          });
        }

        orderList.appendChild(item);
      });
    });
});

// 關閉彈窗
closeModal.addEventListener("click", () => {
  complaintModal.style.display = "none";
});

// 送出表單
complaintForm.addEventListener("submit", function (e) {
  e.preventDefault();
  const reason = document.getElementById("reason").value;

  if (!selectedOrderId) {
    alert("請先選擇一筆訂單");
    return;
  }

  fetch("php/submit_complaint.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      orderId: selectedOrderId,
      reason: reason
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert("申訴送出成功！");
      complaintModal.style.display = "none";
      complaintForm.reset();
      selectedOrderId = null;
    } else {
      alert("送出失敗：" + data.message);
    }
  });
});


// fqa 展開動畫
document.addEventListener("DOMContentLoaded", function () {
  const questions = document.querySelectorAll(".faq-box .question");

  questions.forEach((q) => {
    q.addEventListener("click", () => {
      const answer = q.nextElementSibling;
      const isOpen = answer.classList.contains("open");

      // 關閉所有已開啟的 answer
      document.querySelectorAll(".faq-box .answer.open").forEach(el => {
        el.style.maxHeight = null;
        el.style.paddingTop = "0px";
        el.style.paddingBottom = "0px";
        el.classList.remove("open");
      });

      if (!isOpen) {
        answer.classList.add("open");
        answer.style.paddingTop = "0px";
        answer.style.paddingBottom = "0px";

        // 設定 max-height（先等開啟 class 生效）
        requestAnimationFrame(() => {
          // 手動補 padding 進來前，先設 max-height
          answer.style.maxHeight = answer.scrollHeight + 32 + "px"; // 16 + 16 padding
          answer.style.paddingTop = "16px";
          answer.style.paddingBottom = "16px";
        });
      }
    });
  });
});
