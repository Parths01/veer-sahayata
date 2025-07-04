<?php
session_start();
require_once '../config/database.php';
requireLogin();
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_news':
                $result = addNews($_POST, $_FILES);
                break;
            case 'edit_news':
                $result = editNews($_POST, $_FILES);
                break;
            case 'delete_news':
                $result = deleteNews($_POST['news_id']);
                break;
            case 'toggle_status':
                $result = toggleNewsStatus($_POST['news_id']);
                break;
        }
    }
}

// Get news with pagination and filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM news WHERE 1=1";
$params = [];

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if ($category) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

if ($search) {
    $sql .= " AND (title LIKE ? OR content LIKE ? OR author LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$news = $stmt->fetchAll();

// Get total count for pagination
$countSql = str_replace("SELECT *", "SELECT COUNT(*)", explode("ORDER BY", $sql)[0]);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalNews = $stmt->fetchColumn();
$totalPages = ceil($totalNews / $limit);

// Get news statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'published'")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM news WHERE status = 'draft'")->fetchColumn(),
    'general' => $pdo->query("SELECT COUNT(*) FROM news WHERE category = 'general'")->fetchColumn(),
    'pension' => $pdo->query("SELECT COUNT(*) FROM news WHERE category = 'pension'")->fetchColumn(),
    'health' => $pdo->query("SELECT COUNT(*) FROM news WHERE category = 'health'")->fetchColumn()
];

// Functions
function addNews($data, $files) {
    global $pdo;
    try {
        $imagePath = null;
        
        // Handle image upload
        if (isset($files['image']) && $files['image']['error'] == 0) {
            $uploadDir = '../uploads/news/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $imageExtension = pathinfo($files['image']['name'], PATHINFO_EXTENSION);
            $imageName = 'news_' . time() . '_' . uniqid() . '.' . $imageExtension;
            $imagePath = $uploadDir . $imageName;
            
            if (move_uploaded_file($files['image']['tmp_name'], $imagePath)) {
                $imagePath = 'uploads/news/' . $imageName; // Store relative path
            } else {
                $imagePath = null;
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO news (title, content, excerpt, image, category, author, 
                            publish_date, status, featured) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['excerpt'],
            $imagePath,
            $data['category'],
            $data['author'],
            $data['publish_date'] ?: null,
            $data['status'],
            isset($data['featured']) ? 1 : 0
        ]);
        
        return ['success' => true, 'message' => 'News article added successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function editNews($data, $files) {
    global $pdo;
    try {
        // Get current image path
        $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
        $stmt->execute([$data['news_id']]);
        $currentImage = $stmt->fetchColumn();
        
        $imagePath = $currentImage; // Keep current image by default
        
        // Handle new image upload
        if (isset($files['image']) && $files['image']['error'] == 0) {
            $uploadDir = '../uploads/news/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $imageExtension = pathinfo($files['image']['name'], PATHINFO_EXTENSION);
            $imageName = 'news_' . time() . '_' . uniqid() . '.' . $imageExtension;
            $newImagePath = $uploadDir . $imageName;
            
            if (move_uploaded_file($files['image']['tmp_name'], $newImagePath)) {
                // Delete old image if it exists
                if ($currentImage && file_exists('../' . $currentImage)) {
                    unlink('../' . $currentImage);
                }
                $imagePath = 'uploads/news/' . $imageName; // Store relative path
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE news 
            SET title = ?, content = ?, excerpt = ?, image = ?, category = ?, 
                author = ?, publish_date = ?, status = ?, featured = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['content'],
            $data['excerpt'],
            $imagePath,
            $data['category'],
            $data['author'],
            $data['publish_date'] ?: null,
            $data['status'],
            isset($data['featured']) ? 1 : 0,
            $data['news_id']
        ]);
        
        return ['success' => true, 'message' => 'News article updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function deleteNews($newsId) {
    global $pdo;
    try {
        // Get image path before deletion
        $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
        $stmt->execute([$newsId]);
        $imagePath = $stmt->fetchColumn();
        
        // Delete the news record
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$newsId]);
        
        // Delete associated image file
        if ($imagePath && file_exists('../' . $imagePath)) {
            unlink('../' . $imagePath);
        }
        
        return ['success' => true, 'message' => 'News article deleted successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function toggleNewsStatus($newsId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE news 
            SET status = CASE WHEN status = 'published' THEN 'draft' ELSE 'published' END 
            WHERE id = ?
        ");
        $stmt->execute([$newsId]);
        
        return ['success' => true, 'message' => 'News status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Management - Veer Sahayata Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .news-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .news-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.85em;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .category-general {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .category-pension {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        .category-health {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
        }
        .category-schemes {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: black;
        }
        .category-education {
            background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);
            color: white;
        }
        .news-actions {
            white-space: nowrap;
        }
        .news-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .featured-badge {
            background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);
            color: white;
            font-size: 0.75em;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .excerpt-text {
            max-height: 3em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/admin_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-newspaper text-primary"></i> News Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal">
                                <i class="fas fa-plus"></i> Add New Article
                            </button>
                            <button class="btn btn-outline-secondary" onclick="refreshPage()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if (isset($result)): ?>
                <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo $result['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- News Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['total']; ?></h4>
                                <p class="mb-0">Total Articles</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['published']; ?></h4>
                                <p class="mb-0">Published</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['draft']; ?></h4>
                                <p class="mb-0">Drafts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['general']; ?></h4>
                                <p class="mb-0">General</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['pension']; ?></h4>
                                <p class="mb-0">Pension</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h4><?php echo $stats['health']; ?></h4>
                                <p class="mb-0">Health</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by title, content, or author...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="published" <?php echo $status == 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <option value="general" <?php echo $category == 'general' ? 'selected' : ''; ?>>General</option>
                                    <option value="pension" <?php echo $category == 'pension' ? 'selected' : ''; ?>>Pension</option>
                                    <option value="health" <?php echo $category == 'health' ? 'selected' : ''; ?>>Health</option>
                                    <option value="schemes" <?php echo $category == 'schemes' ? 'selected' : ''; ?>>Schemes</option>
                                    <option value="education" <?php echo $category == 'education' ? 'selected' : ''; ?>>Education</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="news.php" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- News Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>News Articles (<?php echo $totalNews; ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($news)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Category</th>
                                        <th>Author</th>
                                        <th>Status</th>
                                        <th>Publish Date</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($news as $article): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($article['image']): ?>
                                                <img src="../<?php echo htmlspecialchars($article['image']); ?>" 
                                                     alt="News Image" class="news-image me-3">
                                                <?php else: ?>
                                                <div class="news-image me-3 bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                                    <?php if ($article['featured']): ?>
                                                        <span class="featured-badge ms-2">Featured</span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <?php if ($article['excerpt']): ?>
                                                    <small class="text-muted excerpt-text">
                                                        <?php echo htmlspecialchars($article['excerpt']); ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge category-<?php echo $article['category']; ?>">
                                                <?php echo ucfirst($article['category']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-muted">
                                                <?php echo htmlspecialchars($article['author']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $article['status'] == 'published' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($article['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php if ($article['publish_date']): ?>
                                                    <?php echo date('d/m/Y', strtotime($article['publish_date'])); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo date('d/m/Y', strtotime($article['created_at'])); ?><br>
                                                <?php echo date('H:i', strtotime($article['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="news-actions">
                                                <button class="btn btn-sm btn-info" onclick="viewNews('<?php echo $article['id']; ?>')" title="View Article">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="editNews('<?php echo $article['id']; ?>')" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-<?php echo $article['status'] == 'published' ? 'secondary' : 'success'; ?>" 
                                                        onclick="toggleStatus('<?php echo $article['id']; ?>')" 
                                                        title="<?php echo $article['status'] == 'published' ? 'Unpublish' : 'Publish'; ?>">
                                                    <i class="fas fa-<?php echo $article['status'] == 'published' ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteNews('<?php echo $article['id']; ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="News pagination" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h5>No Articles Found</h5>
                            <p class="text-muted">
                                <?php echo $search ? 'No articles match your search criteria.' : 'No news articles have been created yet.'; ?>
                            </p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal">
                                <i class="fas fa-plus"></i> Create First Article
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add News Modal -->
    <div class="modal fade" id="addNewsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_news">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Article Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="general">General</option>
                                        <option value="pension">Pension</option>
                                        <option value="health">Health</option>
                                        <option value="schemes">Schemes</option>
                                        <option value="education">Education</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="excerpt" class="form-label">Excerpt/Summary</label>
                            <textarea class="form-control" id="excerpt" name="excerpt" rows="2" 
                                      placeholder="Brief summary of the article..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Article Content *</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="author" name="author" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['full_name'] ?? 'Admin'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="publish_date" class="form-label">Publish Date</label>
                                    <input type="datetime-local" class="form-control" id="publish_date" name="publish_date">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Featured Image</label>
                                    <input type="file" class="form-control" id="image" name="image" 
                                           accept="image/*" onchange="previewImage(this, 'imagePreview')">
                                    <div id="imagePreview" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1">
                                        <label class="form-check-label" for="featured">
                                            Featured Article
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit News Modal -->
    <div class="modal fade" id="editNewsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editNewsForm">
                    <div class="modal-body" id="editNewsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View News Modal -->
    <div class="modal fade" id="viewNewsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye"></i> Article Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewNewsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '150px';
                    img.className = 'img-thumbnail';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function viewNews(newsId) {
            // Load news details
            fetch('../api/get_news_details.php?id=' + newsId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewNewsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('viewNewsModal')).show();
                    } else {
                        alert('Error loading article details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function editNews(newsId) {
            // Load news edit form
            fetch('../api/get_edit_news_form.php?id=' + newsId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editNewsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editNewsModal')).show();
                    } else {
                        alert('Error loading article details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleStatus(newsId) {
            if (confirm('Are you sure you want to change the status of this article?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="news_id" value="${newsId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteNews(newsId) {
            if (confirm('Are you sure you want to delete this article? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_news">
                    <input type="hidden" name="news_id" value="${newsId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function refreshPage() {
            window.location.reload();
        }

        // Auto-hide success messages
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
