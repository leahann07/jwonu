<?php
// 1. Database Connection
$conn = mysqli_connect("localhost", "root", "", "market");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 2. Handle Order Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $p_name = $_POST['p_name'];
    $qty    = (int)$_POST['qty'];
    $c_name = $_POST['c_name'];
    $cont   = $_POST['contact'];
    $addr   = $_POST['address'];

    $stmt = $conn->prepare("SELECT price, stocks FROM product WHERE name = ?");
    $stmt->bind_param("s", $p_name);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();

    if ($prod && $prod['stocks'] >= $qty) {
        $total_price = (float)$prod['price'] * $qty;
        mysqli_begin_transaction($conn); 
        try {
            $orderStmt = $conn->prepare("INSERT INTO orders (product_name, price, quantity, customer_name, contact, adress) VALUES (?, ?, ?, ?, ?, ?)");
            $orderStmt->bind_param("sdisss", $p_name, $total_price, $qty, $c_name, $cont, $addr);
            $orderStmt->execute();

            $stockStmt = $conn->prepare("UPDATE product SET stocks = stocks - ? WHERE name = ?");
            $stockStmt->bind_param("is", $qty, $p_name);
            $stockStmt->execute();

            mysqli_commit($conn);
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["status" => "error", "message" => "Server Error"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Insufficient Stock!"]);
    }
    exit;
}
$result = mysqli_query($conn, "SELECT * FROM product");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eazy Shop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #ededed; font-family: sans-serif; }
        nav { background: #FF7070; color: white; padding: 12px; text-align: center; }
        
        /* Grid Layout */
        .product-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .product-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; }
        .product { background: white; border-radius: 8px; padding: 10px; text-align: center; transition: 0.3s; }
        .product img { width: 100%; height: 140px; object-fit: contain; margin-bottom: 8px; }
        .price { color: #ee4d2d; font-weight: bold; }

        /* --- CUSTOM MODAL CSS --- */
        .modal-overlay {
            display: none; 
            position: fixed;
            z-index: 2000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease;
        }
        .modal-content-custom {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            position: relative;
            animation: slideDown 0.3s ease-out;
        }
        .modal-header-custom {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px;
        }
        .close-btn { font-size: 28px; cursor: pointer; color: #888; line-height: 1; }
        .close-btn:hover { color: #000; }
        
        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .btn-confirm {
            background-color: #28a745; color: white; border: none;
            padding: 12px; width: 100%; border-radius: 6px; font-weight: bold;
        }
        .btn-confirm:hover { background-color: #218838; }
        
        @media (max-width: 992px) { .product-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 576px) { .product-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

<nav><h3>Eazy Shop</h3></nav>

<div class="product-container">
    <div class="product-grid">
        <?php while ($product = mysqli_fetch_assoc($result)) { 
            $isOut = ($product['stocks'] <= 0); ?>
            <div class="product <?php echo $isOut ? 'opacity-50' : ''; ?>">
                <img src="img/<?php echo $product['image_url']; ?>" alt="Product">
                <h6><?php echo $product['name']; ?></h6>
                <p class="price">₱<?php echo number_format($product['price'], 2); ?></p>
                <button class="btn btn-danger w-100" 
                    onclick="openModal('<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $product['stocks']; ?>, '<?php echo $product['image_url']; ?>')"
                    <?php echo $isOut ? 'disabled' : ''; ?>>
                    <?php echo $isOut ? 'Out of Stock' : 'Buy Now'; ?>
                </button>
            </div>
        <?php } ?>
    </div>
</div>

<div id="customModal" class="modal-overlay">
    <div class="modal-content-custom">
        <div class="modal-header-custom">
            <h5 style="margin:0">Review Your Cart</h5>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="text-center">
            <img id="display_image" src="" style="width: 100px; height: 100px; object-fit: contain; margin-bottom: 10px;">
            <h4 id="display_name" style="color: #ee4d2d;"></h4>
            <p>Unit Price: ₱<span id="display_price"></span></p>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-4">
                <label class="small">Qty:</label>
                <input type="number" id="qty" value="1" min="1" class="form-control" oninput="updateTotal()">
            </div>
            <div class="col-8">
                <label class="small">Subtotal:</label>
                <input type="text" id="display_total" class="form-control fw-bold" readonly>
            </div>
        </div>

        <hr>
        <div class="mb-2">
            <input type="text" id="c_name" class="form-control mb-2" placeholder="Your Name">
            <input type="text" id="contact" class="form-control mb-2" placeholder="Contact #">
            <textarea id="address" class="form-control mb-2" placeholder="Address" rows="2"></textarea>
        </div>

        <button class="btn-confirm" onclick="confirmPurchase()">Confirm Purchase</button>
    </div>
</div>

<script>
    let currentProduct = {};

    function openModal(name, price, stock, img) {
        currentProduct = { name, price, stock, img };
        
        document.getElementById('display_name').innerText = name;
        document.getElementById('display_price').innerText = price.toLocaleString();
        document.getElementById('display_image').src = "img/" + img;
        document.getElementById('qty').max = stock;
        document.getElementById('qty').value = 1;
        
        updateTotal();
        document.getElementById('customModal').style.display = "block";
    }

    function closeModal() {
        document.getElementById('customModal').style.display = "none";
    }

    function updateTotal() {
        const q = document.getElementById('qty').value;
        const total = q * currentProduct.price;
        document.getElementById('display_total').value = "₱" + total.toLocaleString();
    }

    function confirmPurchase() {
        const name = document.getElementById('c_name').value;
        const contact = document.getElementById('contact').value;
        const addr = document.getElementById('address').value;

        if (!name || !contact || !addr) {
            alert("Please complete the shipping details.");
            return;
        }

        // The Confirmation Alert
        if (confirm("Complete purchase for " + currentProduct.name + "?")) {
            placeOrder();
        }
    }

    async function placeOrder() {
        let formData = new FormData();
        formData.append('action', 'place_order');
        formData.append('p_name', currentProduct.name);
        formData.append('qty', document.getElementById('qty').value);
        formData.append('c_name', document.getElementById('c_name').value);
        formData.append('contact', document.getElementById('contact').value);
        formData.append('address', document.getElementById('address').value);

        try {
            let res = await fetch('', { method: 'POST', body: formData });
            let data = await res.json();
            if (data.status === 'success') {
                alert("Purchase Completed! Thank you.");
                location.reload();
            } else {
                alert("Error: " + data.message);
            }
        } catch (e) {
            alert("Server Error.");
        }
    }

    // Close on background click
    window.onclick = function(e) {
        if (e.target == document.getElementById('customModal')) closeModal();
    }
</script>

</body>
</html>