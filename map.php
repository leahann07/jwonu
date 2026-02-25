<?php
$conn = new mysqli("localhost", "root", "", "penk");

if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$search = isset($_GET['search']) ? $_GET['search'] : "";

if ($search != "") {
    $stmt = $conn->prepare("SELECT * FROM map WHERE name LIKE ?");
    $like = "%" . $search . "%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM map");
}

$countries = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $countries[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Map Carousel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
            background: #f8acd3;
            color: #333;
        }

        .top-bar {
            width: 100%;
            background: white;
            padding: 20px;
            text-align: center;
        }

        .container {
            width: 70%;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border: 3px solid #333;
            position: relative;
            overflow: hidden;
        }

        .carousel {
            overflow: hidden;
            width: 100%;
            position: relative;
        }

        .slides {
            display: flex;
            transition: transform 0.5s ease-in-out;
        }

        .slide {
            min-width: 100%;
            text-align: center;
        }

        .slide img {
            max-width: 500px;
            height: auto;
            border: 2px solid #333;
        }

        .country-info {
            padding: 15px;
            color: #333;
        }

        .nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 40px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 10px 15px;
            cursor: pointer;
            z-index: 10;
            border-radius: 5px;
        }

        .prev {
            left: 10px;
        }

        .next {
            right: 10px;
        }
    </style>
</head>

<body>

    <div class="top-bar">
        <form method="GET">
            <input type="text" id="" name="search" placeholder="Search country..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
            <button type="button" onclick="clearSearch()">Clear</button>
        </form>
    </div>

    <div class="container">
        <?php if (count($countries) > 0): ?>
            <div class="carousel">
                <div class="slides" id="slides-container">
                    <?php foreach ($countries as $country): ?>
                        <div class="slide">
                            <img src="img/<?= htmlspecialchars($country['image']) ?>" alt="Flag">
                            <div class="country-info">
                                <strong><?= htmlspecialchars($country['name']) ?></strong>
                                <span>Capital: <?= htmlspecialchars($country['capital']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="nav-btn prev" onclick="moveSlide(-1)">&#10094;</div>
                <div class="nav-btn next" onclick="moveSlide(1)">&#10095;</div>
            </div>
        <?php else: ?>
            <h2 style="text-align:center; color: red;">No country found</h2>
        <?php endif; ?>
    </div>

    <script>
        let index = 0;

        function moveSlide(step) {
            const slidesWrapper = document.getElementById('slides-container');
            const totalSlides = document.querySelectorAll('.slide').length;

            index += step;

            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;

            slidesWrapper.style.transform = 'translateX(' + (-index * 100) + '%)';
        }

        function clearSearch() {
            //Clear the input value
            document.getElementsByName('search')[0].value = '';
            //Redirect to show all results again
            window.location.href = 'map.php';
        }
    </script>
</body>

</html>