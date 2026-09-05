<?php
require_once '../Controllers/Configs.php';
header('Content-Type: application/json; charset=utf-8');

// Chỉ tài khoản quản trị đang đăng nhập mới được dùng API quản lý bài viết.
// Không phụ thuộc API key hard-code.
$isPostAdmin = $Login && !empty($ImS) && (
    (isset($ImS['isQuanTriVien']) && (int)$ImS['isQuanTriVien'] === 1)
    || (isset($ImS['isFounder']) && (int)$ImS['isFounder'] === 1)
    || (isset($ImS['isAdmin']) && (int)$ImS['isAdmin'] === 1)
);

if (!$isPostAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền quản trị.']);
    exit;
}

function runPostQuery($query, $params = [])
{
    global $Connect;
    $stmt = $Connect->prepare($query);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->execute();
    return $stmt;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['postTitle'] ?? '');
    $description = trim($_POST['postDescription'] ?? '');
    $category = isset($_POST['postCategory']) ? (int)$_POST['postCategory'] : 0;

    if ($title === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin.']);
        exit;
    }

    try {
        runPostQuery(
            "INSERT INTO posts (title,description,category,comments,created_at)
             VALUES (:title,:description,:category,:comments,NOW())",
            [
                ':title' => htmlentities($title, ENT_QUOTES, 'UTF-8'),
                ':description' => htmlentities($description, ENT_QUOTES, 'UTF-8'),
                ':category' => $category,
                ':comments' => json_encode([])
            ]
        );
        echo json_encode(['success' => true, 'message' => 'Bài viết đã được đăng thành công.']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Không thể đăng bài viết.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str(file_get_contents('php://input'), $_PUT);
    $id = (int)($_GET['id'] ?? 0);
    $title = trim($_PUT['postTitle'] ?? '');
    $description = trim($_PUT['postDescription'] ?? '');
    $category = isset($_PUT['postCategory']) ? (int)$_PUT['postCategory'] : 0;

    if ($id <= 0 || $title === '' || $description === '') {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    try {
        runPostQuery(
            "UPDATE posts SET title=:title,description=:description,category=:category WHERE id=:id",
            [
                ':title' => htmlentities($title, ENT_QUOTES, 'UTF-8'),
                ':description' => htmlentities($description, ENT_QUOTES, 'UTF-8'),
                ':category' => $category,
                ':id' => $id
            ]
        );
        echo json_encode(['success' => true, 'message' => 'Bài viết đã được cập nhật.']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật bài viết.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        if (isset($_GET['id'])) {
            $stmt = runPostQuery(
                "SELECT id,title,description,category FROM posts WHERE id=:id",
                [':id' => (int)$_GET['id']]
            );
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$post) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết.']);
                exit;
            }
            $post['title'] = html_entity_decode($post['title'], ENT_QUOTES, 'UTF-8');
            $post['description'] = html_entity_decode($post['description'], ENT_QUOTES, 'UTF-8');
            echo json_encode(['success' => true, 'post' => $post]);
        } else {
            $stmt = $Connect->query("SELECT id,title,description,category FROM posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($posts as &$post) {
                $post['title'] = html_entity_decode($post['title'], ENT_QUOTES, 'UTF-8');
                $post['description'] = html_entity_decode($post['description'], ENT_QUOTES, 'UTF-8');
            }
            unset($post);
            echo json_encode(['success' => true, 'posts' => $posts]);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Không thể tải bài viết.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
        exit;
    }

    try {
        $Connect->beginTransaction();

        $check = $Connect->prepare("SELECT id FROM posts WHERE id=:id FOR UPDATE");
        $check->execute([':id' => $id]);
        if (!$check->fetchColumn()) {
            $Connect->rollBack();
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy bài viết.']);
            exit;
        }

        $child = $Connect->prepare("DELETE FROM comments WHERE post_id=:id");
        $child->execute([':id' => $id]);

        $parent = $Connect->prepare("DELETE FROM posts WHERE id=:id");
        $parent->execute([':id' => $id]);

        $Connect->commit();
        echo json_encode(['success' => true, 'message' => 'Đã xóa bài viết và bình luận liên quan.']);
    } catch (Throwable $e) {
        if ($Connect->inTransaction()) {
            $Connect->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Không thể xóa bài viết.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
