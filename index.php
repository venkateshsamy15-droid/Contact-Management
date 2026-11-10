
<?php
session_start();

/* DATABASE CONNECTION */
$conn = new mysqli("localhost", "contact_user", "Contact@2026", "contact_db");

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$message = "";
$messageType = "success";

$formData = [
    "full_name" => "",
    "phone" => "",
    "email" => "",
    "company" => "",
    "address" => ""
];

/* SAFE OUTPUT */
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* VALIDATION FUNCTION */
function validateContact($data) {
    $errors = [];

    $name = trim($data["full_name"] ?? "");
    $phone = trim($data["phone"] ?? "");
    $email = trim($data["email"] ?? "");
    $company = trim($data["company"] ?? "");
    $address = trim($data["address"] ?? "");

    // Name validation
    if ($name === "") {
        $errors[] = "Name is required.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = "Name must contain 2 to 100 characters.";
    } elseif (!preg_match("/^[\p{L}][\p{L} .'-]*$/u", $name)) {
        $errors[] = "Name must contain letters only. Numbers and special symbols are not allowed.";
    }

    // Phone validation
    if ($phone === "") {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
        $errors[] = "Phone number must contain exactly 10 digits.";
    }

    // Email validation (optional)
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (strlen($email) > 150) {
        $errors[] = "Email must not exceed 150 characters.";
    }

    // Company validation (optional)
    if ($company !== "") {
        if (mb_strlen($company) > 100) {
            $errors[] = "Company name must not exceed 100 characters.";
        } elseif (!preg_match("/^[\p{L}\p{N} .,&'()\-]+$/u", $company)) {
            $errors[] = "Company name contains invalid characters.";
        }
    }

    // Address validation (optional)
    if (mb_strlen($address) > 500) {
        $errors[] = "Address must not exceed 500 characters.";
    } elseif (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $address)) {
        $errors[] = "Address contains invalid characters.";
    }

    return $errors;
}

/* DELETE CONTACT */
if (isset($_GET["delete"])) {

    $id = filter_input(INPUT_GET, "delete", FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION["flash"] = "Contact deleted successfully!";
            $_SESSION["flash_type"] = "success";
        } else {
            $_SESSION["flash"] = "Contact not found.";
            $_SESSION["flash_type"] = "error";
        }

        $stmt->close();
    } else {
        $_SESSION["flash"] = "Invalid contact ID.";
        $_SESSION["flash_type"] = "error";
    }

    header("Location: index.php");
    exit;
}

/* ADD / UPDATE CONTACT */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $formData = [
        "full_name" => trim($_POST["full_name"] ?? ""),
        "phone" => trim($_POST["phone"] ?? ""),
        "email" => trim($_POST["email"] ?? ""),
        "company" => trim($_POST["company"] ?? ""),
        "address" => trim($_POST["address"] ?? "")
    ];

    $errors = validateContact($formData);

    if (isset($_POST["add_contact"]) || isset($_POST["update_contact"])) {

        if (!empty($errors)) {
            $message = implode(" ", $errors);
            $messageType = "error";

        } else {

            $name = $formData["full_name"];
            $phone = $formData["phone"];
            $email = $formData["email"];
            $company = $formData["company"];
            $address = $formData["address"];

            if (isset($_POST["add_contact"])) {

                $stmt = $conn->prepare(
                    "INSERT INTO contacts
                    (full_name, phone, email, address, company)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "sssss",
                    $name, $phone, $email, $address, $company
                );

                if ($stmt->execute()) {
                    $_SESSION["flash"] = "Contact added successfully!";
                    $_SESSION["flash_type"] = "success";
                    $stmt->close();

                    header("Location: index.php");
                    exit;
                } else {
                    $message = "Unable to add contact.";
                    $messageType = "error";
                }

                $stmt->close();

            } elseif (isset($_POST["update_contact"])) {

                $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

                if (!$id || $id <= 0) {
                    $message = "Invalid contact ID.";
                    $messageType = "error";
                } else {

                    $stmt = $conn->prepare(
                        "UPDATE contacts SET
                        full_name = ?, phone = ?, email = ?,
                        address = ?, company = ?
                        WHERE id = ?"
                    );

                    $stmt->bind_param(
                        "sssssi",
                        $name, $phone, $email, $address, $company, $id
                    );

                    if ($stmt->execute()) {
                        $_SESSION["flash"] = "Contact updated successfully!";
                        $_SESSION["flash_type"] = "success";
                        $stmt->close();

                        header("Location: index.php");
                        exit;
                    } else {
                        $message = "Unable to update contact.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

/* FLASH MESSAGE */
if (isset($_SESSION["flash"])) {
    $message = $_SESSION["flash"];
    $messageType = $_SESSION["flash_type"] ?? "success";

    unset($_SESSION["flash"], $_SESSION["flash_type"]);
}

/* EDIT CONTACT */
$editContact = null;

if (isset($_GET["edit"])) {

    $editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();
        $editContact = $result->fetch_assoc();

        $stmt->close();
    }

    if (!$editContact) {
        $message = "Contact not found.";
        $messageType = "error";
    }
}

/* FORM VALUES */
if ($editContact && $_SERVER["REQUEST_METHOD"] !== "POST") {
    $formData = [
        "full_name" => $editContact["full_name"],
        "phone" => $editContact["phone"],
        "email" => $editContact["email"],
        "company" => $editContact["company"],
        "address" => $editContact["address"]
    ];
}

/* SEARCH */
$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $like = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT * FROM contacts
        WHERE full_name LIKE ?
        OR phone LIKE ?
        OR email LIKE ?
        OR company LIKE ?
        ORDER BY id DESC"
    );

    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $contacts = $stmt->get_result();

} else {
    $contacts = $conn->query("SELECT * FROM contacts ORDER BY id DESC");
}

/* TOTAL CONTACTS */
$countResult = $conn->query("SELECT COUNT(*) AS total FROM contacts");
$totalContacts = $countResult->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Contact Management System</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f1f5f9;
    color: #1e293b;
}

header {
    background: #172554;
    color: white;
    padding: 22px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.logo {
    font-size: 24px;
    font-weight: bold;
}

.logo span {
    color: #38bdf8;
}

nav a {
    color: white;
    text-decoration: none;
    margin-left: 20px;
}

nav a:hover {
    color: #38bdf8;
}

.container {
    width: 90%;
    max-width: 1250px;
    margin: 35px auto;
}

.welcome {
    margin-bottom: 25px;
}

.welcome h1 {
    font-size: 30px;
    margin-bottom: 8px;
}

.welcome p {
    color: #64748b;
}

.stats {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.05);
    margin-bottom: 30px;
    max-width: 300px;
}

.stats h3 {
    color: #64748b;
    margin-bottom: 12px;
}

.stats h2 {
    color: #2563eb;
    font-size: 36px;
}

.main-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 25px;
    align-items: start;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,.05);
}

.panel h2 {
    font-size: 21px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}

.form-group textarea {
    height: 85px;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus {
    border-color: #2563eb;
}

.hint {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-top: 5px;
}

.btn {
    display: inline-block;
    padding: 11px 17px;
    border: none;
    border-radius: 6px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.btn:hover {
    background: #1d4ed8;
}

.btn-green {
    background: #16a34a;
}

.btn-green:hover {
    background: #15803d;
}

.btn-red {
    background: #dc2626;
}

.btn-red:hover {
    background: #b91c1c;
}

.btn-gray {
    background: #64748b;
}

.message {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 22px;
    background: #dcfce7;
    color: #166534;
    line-height: 1.5;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
}

.search-form {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 13px 10px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #172554;
    color: white;
}

tr:hover {
    background: #f8fafc;
}

.actions {
    display: flex;
    gap: 6px;
}

.actions .btn {
    padding: 8px 10px;
    font-size: 12px;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

footer {
    background: #172554;
    color: white;
    text-align: center;
    padding: 22px;
    margin-top: 50px;
}

@media(max-width: 900px) {
    .main-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 600px) {
    header {
        flex-direction: column;
    }

    nav a {
        margin: 0 7px;
    }

    .container {
        width: 94%;
    }

    .search-form {
        flex-wrap: wrap;
    }
}
</style>
</head>

<body>

<header>
    <div class="logo">Contact<span>Manager</span></div>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="#add-contact">Add Contact</a>
        <a href="#contact-list">Contact List</a>
    </nav>
</header>

<main class="container">

    <div class="welcome">
        <h1>Contact Management Dashboard</h1>
        <p>Manage your contacts easily in one place.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo $messageType === 'error' ? 'error' : ''; ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <h3>Total Contacts</h3>
        <h2><?php echo e($totalContacts); ?></h2>
    </div>

    <div class="main-grid">

        <!-- CONTACT FORM -->
        <section class="panel" id="add-contact">

            <h2>
                <?php echo $editContact ? "Edit Contact" : "Add New Contact"; ?>
            </h2>

            <form method="POST" id="contactForm" novalidate>

                <?php if ($editContact): ?>
                    <input type="hidden" name="id"
                           value="<?php echo e($editContact['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Full Name *</label>

                    <input
                        type="text"
                        name="full_name"
                        id="full_name"
                        placeholder="Enter full name"
                        value="<?php echo e($formData['full_name']); ?>"
                        maxlength="100"
                        autocomplete="name"
                        required
                    >

                    <small class="hint">Letters and spaces only.</small>
                </div>

                <div class="form-group">
                    <label>Phone Number *</label>

                    <input
                        type="tel"
                        name="phone"
                        id="phone"
                        placeholder="Enter 10 digit mobile number"
                        value="<?php echo e($formData['phone']); ?>"
                        maxlength="10"
                        minlength="10"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        autocomplete="tel"
                        required
                    >

                    <small class="hint">Exactly 10 digits. Numbers only.</small>
                </div>

                <div class="form-group">
                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        placeholder="example@gmail.com"
                        value="<?php echo e($formData['email']); ?>"
                        maxlength="150"
                    >

                    <small class="hint">Enter a valid email address.</small>
                </div>

                <div class="form-group">
                    <label>Company</label>

                    <input
                        type="text"
                        name="company"
                        id="company"
                        placeholder="Enter company name"
                        value="<?php echo e($formData['company']); ?>"
                        maxlength="100"
                    >
                </div>

                <div class="form-group">
                    <label>Address</label>

                    <textarea
                        name="address"
                        id="address"
                        maxlength="500"
                        placeholder="Enter address"
                    ><?php echo e($formData['address']); ?></textarea>

                    <small class="hint">Maximum 500 characters.</small>
                </div>

                <?php if ($editContact): ?>

                    <button class="btn btn-green" type="submit"
                            name="update_contact">
                        Update Contact
                    </button>

                    <a href="index.php" class="btn btn-gray">Cancel</a>

                <?php else: ?>

                    <button class="btn btn-green" type="submit"
                            name="add_contact">
                        Save Contact
                    </button>

                    <button class="btn btn-gray" type="reset">
                        Clear
                    </button>

                <?php endif; ?>

            </form>
        </section>

        <!-- CONTACT LIST -->
        <section class="panel" id="contact-list">

            <h2>All Contacts</h2>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search name, phone, email or company..."
                    value="<?php echo e($search); ?>"
                >

                <button type="submit" class="btn">Search</button>

                <?php if ($search !== ""): ?>
                    <a href="index.php" class="btn btn-gray">Clear</a>
                <?php endif; ?>

            </form>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($contacts && $contacts->num_rows > 0): ?>

                        <?php while ($contact = $contacts->fetch_assoc()): ?>

                            <tr>
                                <td><?php echo e($contact['id']); ?></td>
                                <td><?php echo e($contact['full_name']); ?></td>
                                <td><?php echo e($contact['phone']); ?></td>
                                <td><?php echo e($contact['email']); ?></td>
                                <td><?php echo e($contact['company']); ?></td>

                                <td>
                                    <div class="actions">

                                        <a class="btn"
                                           href="index.php?edit=<?php echo e($contact['id']); ?>">
                                            Edit
                                        </a>

                                        <a class="btn btn-red"
                                           href="index.php?delete=<?php echo e($contact['id']); ?>"
                                           onclick="return confirm('Are you sure you want to delete this contact?');">
                                            Delete
                                        </a>

                                    </div>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty">
                                No contacts found. Add your first contact!
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </section>

    </div>
</main>

<footer>
    <p>&copy; 2026 Contact Management System. All Rights Reserved.</p>
</footer>

<script>
const nameInput = document.getElementById("full_name");
const phoneInput = document.getElementById("phone");
const emailInput = document.getElementById("email");
const companyInput = document.getElementById("company");
const addressInput = document.getElementById("address");
const contactForm = document.getElementById("contactForm");

/* NAME: Remove numbers and invalid symbols while typing */
nameInput.addEventListener("input", function() {
    this.value = this.value.replace(/[^\p{L} .'-]/gu, "");
});

/* PHONE: Only 10 digits allowed */
phoneInput.addEventListener("input", function() {
    this.value = this.value.replace(/[^0-9]/g, "").slice(0, 10);
});

/* COMPANY: Allow letters, numbers and common punctuation */
companyInput.addEventListener("input", function() {
    this.value = this.value.replace(/[^\p{L}\p{N} .,&'()\-]/gu, "");
});

/* ADDRESS: Maximum 500 characters */
addressInput.addEventListener("input", function() {
    if (this.value.length > 500) {
        this.value = this.value.slice(0, 500);
    }
});

/* FINAL JAVASCRIPT VALIDATION */
contactForm.addEventListener("submit", function(event) {

    const name = nameInput.value.trim();
    const phone = phoneInput.value.trim();
    const email = emailInput.value.trim();
    const company = companyInput.value.trim();
    const address = addressInput.value.trim();

    const namePattern = /^[\p{L}][\p{L} .'-]*$/u;
    const phonePattern = /^[0-9]{10}$/;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const companyPattern = /^[\p{L}\p{N} .,&'()\-]*$/u;

    if (name.length < 2 || name.length > 100 || !namePattern.test(name)) {
        alert("Name must contain 2 to 100 letters. Numbers are not allowed.");
        event.preventDefault();
        nameInput.focus();
        return;
    }

    if (!phonePattern.test(phone)) {
        alert("Phone number must contain exactly 10 digits.");
        event.preventDefault();
        phoneInput.focus();
        return;
    }

    if (email !== "" && !emailPattern.test(email)) {
        alert("Please enter a valid email address.");
        event.preventDefault();
        emailInput.focus();
        return;
    }

    if (company.length > 100 || !companyPattern.test(company)) {
        alert("Please enter a valid company name.");
        event.preventDefault();
        companyInput.focus();
        return;
    }

    if (address.length > 500) {
        alert("Address must not exceed 500 characters.");
        event.preventDefault();
        addressInput.focus();
        return;
    }
});

/* CONFIRM DELETE */
document.querySelectorAll(".delete-contact").forEach(function(link) {
    link.addEventListener("click", function(event) {
        if (!confirm("Are you sure you want to delete this contact?")) {
            event.preventDefault();
        }
    });
});
</script>

</body>
</html>

<?php
$conn->close();
?>
