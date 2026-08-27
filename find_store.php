<?php
require_once '_base.php';

// Customer must be logged in
if (!isset($_SESSION['users'])) {
    redirect('login.php');
    exit;
}

// Admin cannot access Find Store
if (($_SESSION['users']->role ?? '') === 'admin') {
    redirect('products.php');
    exit;
}

$_title = "Find Store";

function resolve_store_image($store_image) {
    if (empty($store_image)) {
        return null;
    }

    // Already a full URL
    if (preg_match('#^https?://#i', $store_image)) {
        return $store_image;
    }

    // Already includes a folder path
    if (strpos($store_image, '/') !== false) {
        return $store_image;
    }

    // Bare filename - prepend the upload folder
    return STORE_IMAGE_DIR . $store_image;
}

// Get active stores from database
$stmt = $_db->prepare("SELECT * FROM stores WHERE status = 'active' ORDER BY state ASC, store_name ASC");
$stmt->execute();
$stores = $stmt->fetchAll(PDO::FETCH_OBJ);

include '_head.php';
?>

<link rel="stylesheet" href="css/find_store.css">

<!-- Store Introduction -->
<div>
    <p class="eyebrow">SippyGo Locations</p>
    <h2>Find Your Nearest Store</h2>
</div>

<!-- Search Bar -->
<div class="search-container">

    <input
        type="text"
        id="storeSearch"
        placeholder="Search by store name, city or state"
    >

    <select id="stateFilter">
        <option value="">All States</option>
        <option value="Kuala Lumpur">Kuala Lumpur</option>
        <option value="Selangor">Selangor</option>
        <option value="Penang">Penang</option>
        <option value="Johor">Johor</option>
        <option value="Perak">Perak</option>
        <option value="Melaka">Melaka</option>
        <option value="Negeri Sembilan">Negeri Sembilan</option>
        <option value="Pahang">Pahang</option>
        <option value="Kedah">Kedah</option>
        <option value="Perlis">Perlis</option>
        <option value="Kelantan">Kelantan</option>
        <option value="Terengganu">Terengganu</option>
        <option value="Sabah">Sabah</option>
        <option value="Sarawak">Sarawak</option>
    </select>

    <button type="button" id="searchBtn">
        Search
    </button>

</div>

<!-- Store Count -->
<p id="storeCount"></p>

<!-- Store List -->
<div class="product-grid" id="storeGrid">

    <?php foreach ($stores as $store): ?>

        <?php
        // Create Google Maps link
        $maps_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($store->map_query);
        ?>

        <div
            class="product-card store-card"
            data-name="<?= encode($store->store_name) ?>"
            data-address="<?= encode($store->address) ?>"
            data-state="<?= encode($store->state) ?>"
        >

            <!-- Store Image -->
            <?php $store_image_url = resolve_store_image($store->store_image); ?>
            <?php if ($store_image_url): ?>

                <div class="store-image-container">
                    <img
                        src="<?= encode($store_image_url) ?>"
                        alt="<?= encode($store->store_name) ?>"
                        class="store-image"
                        loading="lazy"
                        onerror="this.parentElement.style.display='none'"
                    >
                </div>

            <?php endif; ?>

            <!-- Store Information -->
            <div>

                <p class="eyebrow">
                    <?= encode($store->state) ?>
                </p>

                <h3>
                    <?= encode($store->store_name) ?>
                </h3>

                <p>
                    <?= nl2br(encode($store->address)) ?>
                </p>

                <p>
                    <strong>Opening Hours</strong><br>
                    <?= encode($store->opening_hours) ?>
                </p>

                <p>
                    <strong>Contact</strong><br>
                    <?= encode($store->contact) ?>
                </p>

            </div>

            <!-- Get Directions -->
            <a
                href="<?= encode($maps_url) ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="nav-link nav-link-btn"
            >
                Get Directions
            </a>

        </div>

    <?php endforeach; ?>

</div>

<!-- No Store Found -->
<div id="noStore" style="display: none;">
    <div class="product-card">

        <h3>No Stores Found</h3>

        <p>
            Try searching using another store name,
            city or state.
        </p>

    </div>

</div>

<script>

const searchInput = document.getElementById('storeSearch');
const searchButton = document.getElementById('searchBtn');
const stateFilter = document.getElementById('stateFilter');

const storeCards = document.querySelectorAll('.store-card');
const storeCount = document.getElementById('storeCount');
const noStore = document.getElementById('noStore');

function filterStores() {

    const search = searchInput.value.toLowerCase().trim();
    const selectedState = stateFilter.value.toLowerCase().trim();

    let found = 0;

    storeCards.forEach(function(card) {

        const name = (card.dataset.name || '').toLowerCase();
        const address = (card.dataset.address || '').toLowerCase();
        const state = (card.dataset.state || '').toLowerCase();

        const matchesSearch =
            search === '' ||
            name.includes(search) ||
            address.includes(search) ||
            state.includes(search);

        const matchesState =
            selectedState === '' ||
            state === selectedState;

        if (matchesSearch && matchesState) {
            card.style.display = '';
            found++;
        } else {
            card.style.display = 'none';
        }
    });

    // Store count
    if (found === 1) {
        storeCount.textContent = '1 store found';
    } else {
        storeCount.textContent = found + ' stores found';
    }

    // No stores found
    if (found === 0) {
        noStore.style.display = 'block';
    } else {
        noStore.style.display = 'none';
    }
}

// Search button
searchButton.addEventListener('click', filterStores);


// Filter when state changes
stateFilter.addEventListener('change', filterStores);


// Search while typing
searchInput.addEventListener('input', filterStores);


// Enter key
searchInput.addEventListener('keydown', function(event) {

    if (event.key === 'Enter') {
        event.preventDefault();
        filterStores();
    }

});


// Initial display
filterStores();

</script>

<?php
include '_foot.php';
?>