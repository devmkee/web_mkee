<?php
// Thiết lập header để trả về JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// (1) THIẾT LẬP KẾT NỐI DATABASE TẠI ĐÂY (Sử dụng PDO hoặc mysqli)
$pdo = new PDO('mysql:host=localhost;dbname=your_db_name', 'username', 'password');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        // Lấy tất cả bình luận gốc (parent_id IS NULL)
        $stmt = $pdo->query("SELECT * FROM comments WHERE parent_id IS NULL ORDER BY created_at DESC");
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lấy replies cho mỗi comment (Có thể tối ưu bằng cách JOIN hoặc Subquery)
        foreach ($comments as &$comment) {
            $stmt_r = $pdo->prepare("SELECT * FROM comments WHERE parent_id = ? ORDER BY created_at ASC");
            $stmt_r->execute([$comment['id']]);
            $comment['replies'] = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['status' => 'success', 'data' => $comments]);
        break;

    case 'post':
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? 'Anonymous';
        $content = $data['content'] ?? '';
        $parentId = $data['parent_id'] ?? NULL;
        
        if (empty($content)) {
            echo json_encode(['status' => 'error', 'message' => 'Content cannot be empty']);
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO comments (username, content, parent_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, $content, $parentId]);
        
        echo json_encode(['status' => 'success', 'new_comment_id' => $pdo->lastInsertId()]);
        break;

    case 'like':
        $data = json_decode(file_get_contents('php://input'), true);
        $commentId = $data['comment_id'] ?? 0;
        
        // Cập nhật likes_count
        $pdo->prepare("UPDATE comments SET likes_count = likes_count + 1 WHERE id = ?")
            ->execute([$commentId]);
        
        // Lấy lại số like mới nhất để trả về
        $newCount = $pdo->prepare("SELECT likes_count FROM comments WHERE id = ?");
        $newCount->execute([$commentId]);
        
        echo json_encode(['status' => 'success', 'new_likes_count' => $newCount->fetchColumn()]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>