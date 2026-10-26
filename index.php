<?php
require "db.php";

session_start();

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";
$editStudent = null;

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        http_response_code(403);
        exit("Invalid request token.");
    }

    $action = $_POST["action"] ?? "";

    try {
        if ($action === "save") {
            $id = (int)($_POST["id"] ?? 0);
            $name = trim($_POST["name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $course = trim($_POST["course"] ?? "");

            if (
                $name === "" ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                $course === ""
            ) {
                throw new Exception(
                    "Enter a valid name, email, and course."
                );
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE students
                     SET name = ?, email = ?, course = ?
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    "sssi", $name, $email, $course, $id
                );
                $stmt->execute();

                header("Location: index.php?msg=updated");
                exit;
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO students (name, email, course)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param(
                    "sss", $name, $email, $course
                );
                $stmt->execute();

                header("Location: index.php?msg=added");
                exit;
            }
        }

        if ($action === "delete") {
            $id = (int)($_POST["id"] ?? 0);

            $stmt = $conn->prepare(
                "DELETE FROM students WHERE id = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            header("Location: index.php?msg=deleted");
            exit;
        }
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() === 1062) {
            $error = "This email address already exists.";
        } else {
            $error = "Database operation failed.";
        }
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

$messages = [
    "added" => "Student added successfully.",
    "updated" => "Student updated successfully.",
    "deleted" => "Student deleted successfully."
];

if (isset($_GET["msg"]) && isset($messages[$_GET["msg"]])) {
    $message = $messages[$_GET["msg"]];
}

if (isset($_GET["edit"])) {
    $id = (int)$_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT id, name, email, course
         FROM students WHERE id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editStudent = $stmt->get_result()->fetch_assoc();

    if (!$editStudent) {
        $error = "Student record not found.";
    }
}

$search = trim($_GET["search"] ?? "");
$courseFilter = trim($_GET["course"] ?? "");

$sql = "SELECT id, name, email, course, created_at
        FROM students
        WHERE (name LIKE ? OR email LIKE ? OR course LIKE ?)
        AND (? = '' OR course = ?)
        ORDER BY id DESC";

$term = "%" . $search . "%";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "sssss",
    $term,
    $term,
    $term,
    $courseFilter,
    $courseFilter
);
$stmt->execute();
$result = $stmt->get_result();

$courses = $conn->query(
    "SELECT DISTINCT course FROM students ORDER BY course"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Management System</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f4f6f9;
        }
        h1, h2 { color: #23395d; }
        .panel {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        input, select, button {
            padding: 9px;
            margin: 5px 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th { background: #e8edf5; }
        .success { color: green; }
        .error { color: #c62828; }
        a { margin-right: 8px; }
    </style>
</head>

<body>
    <h1>Student Management System</h1>

    <?php if ($message): ?>
        <p class="success"><?= e($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <div class="panel">
        <h2><?= $editStudent ? "Edit Student" : "Add Student" ?></h2>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION["csrf_token"]) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"
                   value="<?= e($editStudent["id"] ?? 0) ?>">

            <input type="text" name="name"
                   placeholder="Student Name"
                   maxlength="100" required
                   value="<?= e($editStudent["name"] ?? "") ?>">

            <input type="email" name="email"
                   placeholder="Email Address"
                   maxlength="150" required
                   value="<?= e($editStudent["email"] ?? "") ?>">

            <select name="course" required>
                <option value="">Select Course</option>
                <?php
                $courseOptions = ["BCA", "BSc IT", "MCA"];
                foreach ($courseOptions as $c):
                ?>
                    <option value="<?= e($c) ?>"
                        <?= ($editStudent["course"] ?? "") === $c
                            ? "selected" : "" ?>>
                        <?= e($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">
                <?= $editStudent ? "Update Student" : "Add Student" ?>
            </button>

            <?php if ($editStudent): ?>
                <a href="index.php">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="panel">
        <h2>Search and Filter Students</h2>

        <form method="GET">
            <input type="text" name="search"
                   placeholder="Search name, email, or course"
                   value="<?= e($search) ?>">

            <select name="course">
                <option value="">All Courses</option>
                <?php while ($c = $courses->fetch_assoc()): ?>
                    <option value="<?= e($c["course"]) ?>"
                        <?= $courseFilter === $c["course"]
                            ? "selected" : "" ?>>
                        <?= e($c["course"]) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit">Search / Filter</button>
            <a href="index.php">Reset</a>
        </form>
    </div>

    <div class="panel">
        <h2>Student Records</h2>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row["id"]) ?></td>
                        <td><?= e($row["name"]) ?></td>
                        <td><?= e($row["email"]) ?></td>
                        <td><?= e($row["course"]) ?></td>
                        <td><?= e($row["created_at"]) ?></td>
                        <td>
                            <a href="index.php?edit=<?= (int)$row["id"] ?>">
                                Edit
                            </a>

                            <form method="POST" style="display:inline"
                                onsubmit="return confirm(
                                    'Are you sure you want to delete this student?'
                                );">
                                <input type="hidden" name="csrf_token"
                                    value="<?= e($_SESSION["csrf_token"]) ?>">
                                <input type="hidden" name="action"
                                    value="delete">
                                <input type="hidden" name="id"
                                    value="<?= (int)$row["id"] ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No student records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>