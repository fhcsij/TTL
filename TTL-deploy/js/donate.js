// 記錄點了哪一個類別
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', () => {
        const category = card.getAttribute('data-category');
        document.getElementById('donate-category').value = category;
    });
});

// 取得分類清單 放入 捐贈商品 的下拉選單
fetch("php/get_categories.php")
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const donateSelect = document.getElementById("donate-category");
            data.categories.forEach(c => {
                const option = document.createElement("option");
                option.value = c.id;
                option.textContent = c.name;
                donateSelect.appendChild(option);
            });
        }
    });

// ✅ 捐贈商品提交
document.getElementById("donateProductForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch("php/donate_product.php", {
        method: "POST",
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("商品捐贈成功！");
                bootstrap.Modal.getInstance(document.getElementById("donateModal")).hide();
                location.reload(); // ✅ 重新載入頁面（點數也會刷新）
            } else {
                alert("捐贈失敗：" + data.msg);
            }
        })
        .catch(err => {
            console.error("捐贈失敗", err);
            alert("伺服器錯誤");
        });
});

