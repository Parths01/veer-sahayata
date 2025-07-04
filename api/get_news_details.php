<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'News ID is required']);
    exit;
}

$newsId = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch();

    if (!$news) {
        echo json_encode(['success' => false, 'message' => 'Article not found']);
        exit;
    }

    // Format the news details HTML
    $html = '
    <div class="row">
        <div class="col-md-12">
            ' . ($news['image'] ? '
            <div class="text-center mb-4">
                <img src="../' . htmlspecialchars($news['image']) . '" 
                     alt="Article Image" class="img-fluid rounded" style="max-height: 300px;">
            </div>
            ' : '') . '
            
            <div class="mb-3">
                <h4>' . htmlspecialchars($news['title']) . '</h4>
                ' . ($news['featured'] ? '<span class="badge bg-danger">Featured</span>' : '') . '
                <span class="badge bg-' . (strtolower($news['category']) == 'general' ? 'secondary' : 
                    (strtolower($news['category']) == 'pension' ? 'primary' : 
                    (strtolower($news['category']) == 'health' ? 'success' : 
                    (strtolower($news['category']) == 'schemes' ? 'warning' : 'info')))) . ' ms-2">' . 
                    ucfirst($news['category']) . '</span>
            </div>
            
            ' . ($news['excerpt'] ? '
            <div class="mb-3">
                <h6>Summary:</h6>
                <p class="text-muted">' . nl2br(htmlspecialchars($news['excerpt'])) . '</p>
            </div>
            ' : '') . '
            
            <div class="mb-4">
                <h6>Content:</h6>
                <div>' . nl2br(htmlspecialchars($news['content'])) . '</div>
            </div>
        </div>
    </div>
    
    <hr class="my-4">
    
    <div class="row">
        <div class="col-md-6">
            <h6><i class="fas fa-info-circle text-primary"></i> Article Information</h6>
            
            <div class="mb-2">
                <strong>Author:</strong> <span class="text-muted">' . htmlspecialchars($news['author']) . '</span>
            </div>
            
            <div class="mb-2">
                <strong>Status:</strong> 
                <span class="badge bg-' . ($news['status'] == 'published' ? 'success' : 'secondary') . '">' . 
                ucfirst($news['status']) . '</span>
            </div>
            
            ' . ($news['publish_date'] ? '
            <div class="mb-2">
                <strong>Publish Date:</strong> <span class="text-muted">' . 
                date('d M Y, H:i', strtotime($news['publish_date'])) . '</span>
            </div>
            ' : '') . '
            
            <div class="mb-2">
                <strong>Created:</strong> <span class="text-muted">' . 
                date('d M Y, H:i', strtotime($news['created_at'])) . '</span>
            </div>
            
            ' . ($news['updated_at'] ? '
            <div class="mb-2">
                <strong>Last Updated:</strong> <span class="text-muted">' . 
                date('d M Y, H:i', strtotime($news['updated_at'])) . '</span>
            </div>
            ' : '') . '
        </div>
        
        <div class="col-md-6">
            <h6><i class="fas fa-cog text-primary"></i> Article Settings</h6>
            
            <div class="mb-2">
                <strong>Featured Article:</strong> 
                <span class="badge bg-' . ($news['featured'] ? 'success' : 'secondary') . '">' . 
                ($news['featured'] ? 'Yes' : 'No') . '</span>
            </div>
            
            <div class="mb-2">
                <strong>Category:</strong> 
                <span class="badge bg-' . (strtolower($news['category']) == 'general' ? 'secondary' : 
                    (strtolower($news['category']) == 'pension' ? 'primary' : 
                    (strtolower($news['category']) == 'health' ? 'success' : 
                    (strtolower($news['category']) == 'schemes' ? 'warning' : 'info')))) . '">' . 
                    ucfirst($news['category']) . '</span>
            </div>
            
            ' . ($news['image'] ? '
            <div class="mb-2">
                <strong>Featured Image:</strong> <span class="text-success">Uploaded</span>
            </div>
            ' : '
            <div class="mb-2">
                <strong>Featured Image:</strong> <span class="text-muted">None</span>
            </div>
            ') . '
        </div>
    </div>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
