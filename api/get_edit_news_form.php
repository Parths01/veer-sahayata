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

    // Format publish date for datetime-local input
    $publishDate = $news['publish_date'] ? date('Y-m-d\TH:i', strtotime($news['publish_date'])) : '';

    // Format the edit form HTML
    $html = '
    <input type="hidden" name="action" value="edit_news">
    <input type="hidden" name="news_id" value="' . $news['id'] . '">
    
    <div class="row">
        <div class="col-md-8">
            <div class="mb-3">
                <label for="edit_title" class="form-label">Article Title *</label>
                <input type="text" class="form-control" id="edit_title" name="title" 
                       value="' . htmlspecialchars($news['title']) . '" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="mb-3">
                <label for="edit_category" class="form-label">Category *</label>
                <select class="form-select" id="edit_category" name="category" required>
                    <option value="">Select Category</option>
                    <option value="general"' . ($news['category'] == 'general' ? ' selected' : '') . '>General</option>
                    <option value="pension"' . ($news['category'] == 'pension' ? ' selected' : '') . '>Pension</option>
                    <option value="health"' . ($news['category'] == 'health' ? ' selected' : '') . '>Health</option>
                    <option value="schemes"' . ($news['category'] == 'schemes' ? ' selected' : '') . '>Schemes</option>
                    <option value="education"' . ($news['category'] == 'education' ? ' selected' : '') . '>Education</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="mb-3">
        <label for="edit_excerpt" class="form-label">Excerpt/Summary</label>
        <textarea class="form-control" id="edit_excerpt" name="excerpt" rows="2" 
                  placeholder="Brief summary of the article...">' . htmlspecialchars($news['excerpt']) . '</textarea>
    </div>
    
    <div class="mb-3">
        <label for="edit_content" class="form-label">Article Content *</label>
        <textarea class="form-control" id="edit_content" name="content" rows="8" required>' . htmlspecialchars($news['content']) . '</textarea>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_author" class="form-label">Author</label>
                <input type="text" class="form-control" id="edit_author" name="author" 
                       value="' . htmlspecialchars($news['author']) . '">
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_publish_date" class="form-label">Publish Date</label>
                <input type="datetime-local" class="form-control" id="edit_publish_date" name="publish_date"
                       value="' . $publishDate . '">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_image" class="form-label">Featured Image</label>
                ' . ($news['image'] ? '
                <div class="mb-2">
                    <img src="../' . htmlspecialchars($news['image']) . '" alt="Current Image" 
                         class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                    <br><small class="text-muted">Current image (upload new to replace)</small>
                </div>
                ' : '') . '
                <input type="file" class="form-control" id="edit_image" name="image" 
                       accept="image/*" onchange="previewImage(this, \'editImagePreview\')">
                <div id="editImagePreview" class="mt-2"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select class="form-select" id="edit_status" name="status">
                    <option value="draft"' . ($news['status'] == 'draft' ? ' selected' : '') . '>Draft</option>
                    <option value="published"' . ($news['status'] == 'published' ? ' selected' : '') . '>Published</option>
                </select>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="edit_featured" name="featured" value="1"' . 
                    ($news['featured'] ? ' checked' : '') . '>
                    <label class="form-check-label" for="edit_featured">
                        Featured Article
                    </label>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = "";
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.style.maxWidth = "200px";
                    img.style.maxHeight = "150px";
                    img.className = "img-thumbnail";
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>';

    echo json_encode(['success' => true, 'html' => $html]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
