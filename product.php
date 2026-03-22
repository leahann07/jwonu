<?php
// 1. Database Connection
$conn = mysqli_connect("localhost", "root", "", "market");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 2. Handle Order Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    // Collect data from JavaScript fetch
    $p_name = $_POST['p_name'];
    $qty    = (int)$_POST['qty'];
    $c_name = $_POST['c_name'];
    $cont   = $_POST['contact'];
    $addr   = $_POST['address'];

    // Get current price and stock using Prepared Statements (Safer)
    $stmt = $conn->prepare("SELECT price, stocks FROM product WHERE name = ?");
    $stmt->bind_param("s", $p_name);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();

    if ($prod && $prod['stocks'] >= $qty) {
        $total_price = (float)$prod['price'] * $qty;

        mysqli_begin_transaction($conn);
        try {
            // Insert into orders (using 'adress' as per your schema)
            $orderStmt = $conn->prepare("INSERT INTO orders (product_name, price, quantity, customer_name, contact, adress) VALUES (?, ?, ?, ?, ?, ?)");
            $orderStmt->bind_param("sdisss", $p_name, $total_price, $qty, $c_name, $cont, $addr);
            $orderStmt->execute();

            // Deduct stock
            $stockStmt = $conn->prepare("UPDATE product SET stocks = stocks - ? WHERE name = ?");
            $stockStmt->bind_param("is", $qty, $p_name);
            $stockStmt->execute();

            mysqli_commit($conn);
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage()]);
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
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background-color: #ededed;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    nav {
        background: #FF7070;
        color: white;
        padding: 12px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    /* Adjusted Container: Max-width 1100px is sweet spot for 5 items */
    .product-container {
        max-width: 1100px; 
        margin: 0 auto;
        padding: 20px;
    }

    /* 5 Columns Logic */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr); 
        gap: 12px; /* Smaller gap for more items */
    }

    .product {
        background: white;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: 0.3s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .product:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .product img {
        width: 100%;
        height: 140px; /* Reduced height to fit 5 per row better */
        object-fit: contain;
        background: #fdfdfd;
        border-radius: 4px;
        margin-bottom: 8px;
    }

    .info h6 {
        font-size: 0.9rem; /* Smaller text for compact look */
        height: 2.4em;
        overflow: hidden;
        margin-bottom: 5px;
    }

    .price {
        color: #ee4d2d;
        font-weight: bold;
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .out-of-stock {
        opacity: 0.6;
        filter: grayscale(1);
    }

    #buy {
        background-color: #FF7070;
        border: none;
        font-size: 0.85rem;
        padding: 8px;
    }

    #buy:hover {
        background-color: #9E3B3B;
    }

    /* Responsive: Adjust columns for smaller screens */
    @media (max-width: 992px) {
        .product-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 576px) {
        .product-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<nav>
    <h3>Eazy Shop</h3>
</nav>

<div class="product-container">
    <h4 class="text-center mb-4">Featured Products</h4>
    
    <div class="product-grid">
        <?php while ($product = mysqli_fetch_assoc($result)) {
            $isOut = ($product['stocks'] <= 0);
        ?>
            <div class="product <?php echo $isOut ? 'out-of-stock' : ''; ?>">
                <img src="img/<?php echo $product['image_url']; ?>" alt="Product">

                <div class="info">
                    <h6 class="mb-1"><?php echo $product['name']; ?></h6>
                    <p class="price">₱<?php echo number_format($product['price'], 2); ?></p>
                    <p class="small mb-2 <?php echo $isOut ? 'text-danger' : 'text-muted'; ?>">
                        <?php echo $isOut ? 'Out of Stock' : 'Stock: ' . $product['stocks']; ?>
                    </p>

                    <button id="buy" class="btn <?php echo $isOut ? 'btn-secondary' : 'btn-danger'; ?> w-100"
                        onclick="openModal('<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $product['stocks']; ?>)"
                        <?php echo $isOut ? 'disabled' : ''; ?>>
                        <?php echo $isOut ? 'Sold Out' : 'Buy Now'; ?>
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

    <div class="modal fade" id="buyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Your Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h4 id="display_name" class="text-primary"></h4>
                    <p class="fw-bold">Price: ₱<span id="display_price"></span></p>

                    <div class="row g-2">
                        <div class="col-4">
                            <label class="small">Quantity:</label>
                            <input type="number" id="qty" value="1" min="1" class="form-control" onchange="updateTotal()">
                        </div>
                        <div class="col-8">
                            <label class="small">Subtotal:</label>
                            <input type="text" id="display_total" class="form-control fw-bold" readonly>
                        </div>
                    </div>

                    <hr>
                    <div class="mb-2">
                        <input type="text" id="c_name" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="mb-2">
                        <input type="text" id="contact" class="form-control" placeholder="Contact Number" required>
                    </div>
                    <div class="mb-2">
                        <textarea id="address" class="form-control" placeholder="Shipping Address" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success w-100 py-2" onclick="placeOrder()">Confirm Order</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentProduct = {};
        const myModal = new bootstrap.Modal(document.getElementById('buyModal'));

        function openModal(name, price, stock) {
            currentProduct = {
                name,
                price,
                stock
            };
            document.getElementById('display_name').innerText = name;
            document.getElementById('display_price').innerText = price.toLocaleString();
            document.getElementById('qty').max = stock;
            document.getElementById('qty').value = 1;
            updateTotal();
            myModal.show();
        }

        function updateTotal() {
            const q = document.getElementById('qty').value;
            const total = q * currentProduct.price;
            document.getElementById('display_total').value = "₱" + total.toLocaleString();
        }

        async function placeOrder() {
            const qtyInput = document.getElementById('qty').value;
            const cName = document.getElementById('c_name').value;
            const cContact = document.getElementById('contact').value;
            const cAddr = document.getElementById('address').value;

            if (!cName || !cContact || !cAddr) {
                alert("Please fill in all customer details.");
                return;
            }

            let formData = new FormData();
            formData.append('action', 'place_order'); // Match PHP line 10
            formData.append('p_name', currentProduct.name);
            formData.append('qty', qtyInput);
            formData.append('c_name', cName);
            formData.append('contact', cContact);
            formData.append('address', cAddr);

            try {
                let response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                let result = await response.json();

                if (result.status === 'success') {
                    alert('Success! Your order has been placed.');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Failed to connect to the server.');
            }
        }
    </script>
</body>

</html>