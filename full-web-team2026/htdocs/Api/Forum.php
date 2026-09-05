<?php
include '../Controllers/Configs.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
$response = ['status' => 'error', 'message' => 'Yêu cầu không hợp lệ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'addComment') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $comment_content = trim($_POST['comment_content'] ?? '');
    $username = $_SESSION['ImSynZx_Login'] ?? null;

    if (!$post_id || empty($comment_content) || !$username) {
        $response['message'] = 'Dữ liệu không hợp lệ';
    } else {
        $gender = isset($IHero['gender']) ? intval($IHero['gender']) : 0;
        $stmt = $Connect->prepare("SELECT comments FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        if (!$post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $response['message'] = 'Bài viết không tồn tại';
        } else {
            $comments = json_decode($post['comments'], true) ?: [];
            $new_comment = [
                'name'       => $username,
                'content'    => $comment_content,
                'created_at' => date('Y-m-d H:i:s'),
                'gender'     => $gender
            ];
            $comments[] = $new_comment;
            $stmt = $Connect->prepare("UPDATE posts SET comments = ? WHERE id = ?");
            if ($stmt->execute([json_encode($comments), $post_id])) {
                $response = ['status' => 'success', 'message' => 'Bình luận đã được thêm', 'comment' => $new_comment];
            } else {
                $response['message'] = 'Không thể cập nhật bình luận';
            }
        }
    }
}

echo json_encode($response);
