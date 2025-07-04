<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get help categories and topics
$help_categories = [
    'getting_started' => [
        'title' => 'Getting Started',
        'icon' => 'fas fa-play-circle',
        'color' => 'primary',
        'topics' => [
            ['title' => 'Creating Your Profile', 'content' => 'Learn how to set up your complete profile with all necessary information.'],
            ['title' => 'Document Upload Process', 'content' => 'Step-by-step guide on uploading and managing your documents.'],
            ['title' => 'Navigation Guide', 'content' => 'Understanding the dashboard and main navigation features.'],
            ['title' => 'Account Security', 'content' => 'Tips for keeping your account secure and managing passwords.']
        ]
    ],
    'pension_services' => [
        'title' => 'Pension Services',
        'icon' => 'fas fa-money-check-alt',
        'color' => 'success',
        'topics' => [
            ['title' => 'Pension Calculation', 'content' => 'How pension amounts are calculated based on rank and service years.'],
            ['title' => 'Pension Application Process', 'content' => 'Complete guide to applying for pension benefits.'],
            ['title' => 'Required Documents', 'content' => 'List of documents needed for pension processing.'],
            ['title' => 'Status Tracking', 'content' => 'How to track your pension application status.']
        ]
    ],
    'verification' => [
        'title' => 'Verification Process',
        'icon' => 'fas fa-shield-check',
        'color' => 'warning',
        'topics' => [
            ['title' => 'Identity Verification', 'content' => 'Process for verifying your identity and service records.'],
            ['title' => 'Document Verification', 'content' => 'How your uploaded documents are reviewed and verified.'],
            ['title' => 'Live Verification', 'content' => 'Understanding the live verification process via video call.'],
            ['title' => 'Verification Status', 'content' => 'Checking and understanding your verification status.']
        ]
    ],
    'schemes_benefits' => [
        'title' => 'Schemes & Benefits',
        'icon' => 'fas fa-gift',
        'color' => 'info',
        'topics' => [
            ['title' => 'Available Schemes', 'content' => 'Overview of all welfare schemes available to defence personnel.'],
            ['title' => 'Eligibility Criteria', 'content' => 'Understanding eligibility requirements for different schemes.'],
            ['title' => 'Application Process', 'content' => 'How to apply for various government schemes and benefits.'],
            ['title' => 'Benefits Tracking', 'content' => 'Monitoring your applications and received benefits.']
        ]
    ],
    'technical_support' => [
        'title' => 'Technical Support',
        'icon' => 'fas fa-tools',
        'color' => 'danger',
        'topics' => [
            ['title' => 'Login Issues', 'content' => 'Troubleshooting common login problems and password reset.'],
            ['title' => 'Document Upload Problems', 'content' => 'Resolving issues with file uploads and format requirements.'],
            ['title' => 'Browser Compatibility', 'content' => 'Supported browsers and technical requirements.'],
            ['title' => 'Mobile App Usage', 'content' => 'Using the portal on mobile devices and tablets.']
        ]
    ],
    'contact_support' => [
        'title' => 'Contact & Support',
        'icon' => 'fas fa-headset',
        'color' => 'secondary',
        'topics' => [
            ['title' => 'Help Desk', 'content' => 'Contacting our support team for assistance.'],
            ['title' => 'Regional Offices', 'content' => 'Finding your nearest regional office for in-person help.'],
            ['title' => 'Emergency Contact', 'content' => 'Emergency contact numbers for urgent issues.'],
            ['title' => 'Feedback', 'content' => 'Providing feedback to improve our services.']
        ]
    ]
];

// Frequently Asked Questions
$faqs = [
    [
        'question' => 'How do I reset my password?',
        'answer' => 'Click on "Forgot Password" on the login page, enter your registered email or service number, and follow the instructions sent to your email.'
    ],
    [
        'question' => 'What documents are required for pension application?',
        'answer' => 'You need your service record, identity proof, bank details, and discharge certificate. Additional documents may be required based on your specific case.'
    ],
    [
        'question' => 'How long does the verification process take?',
        'answer' => 'Document verification typically takes 3-5 working days. Live verification appointments are usually scheduled within 7 days of application.'
    ],
    [
        'question' => 'Can I update my documents after submission?',
        'answer' => 'Yes, you can upload revised documents through your dashboard. The previous version will be marked as superseded.'
    ],
    [
        'question' => 'How do I track my application status?',
        'answer' => 'Login to your dashboard and go to the "My Applications" section to see real-time status updates of all your submissions.'
    ],
    [
        'question' => 'What should I do if my verification is rejected?',
        'answer' => 'Check the rejection reason in your dashboard, upload the corrected documents, and resubmit. You can also contact support for guidance.'
    ],
    [
        'question' => 'How do I add family members as dependents?',
        'answer' => 'Go to your profile, click on "Manage Dependents", and add family members with their required documents and relationship proof.'
    ],
    [
        'question' => 'Is my personal information secure?',
        'answer' => 'Yes, we use bank-level security encryption. Your data is protected and used only for official welfare and pension purposes.'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Veer Sahayata</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/user_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/user_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-question-circle text-primary me-2"></i>Help & Support Center</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Help Header -->
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body text-center py-4">
                        <h3>Find answers to your questions and get the support you need</h3>
                        
                        <!-- Search Box -->
                        <div class="row justify-content-center mt-3">
                            <div class="col-md-6">
                                <div class="input-group help-search">
                                    <input type="text" class="form-control" placeholder="Search for help topics..." id="helpSearch">
                                    <button class="btn btn-light" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center help-card">
                            <div class="card-body">
                                <i class="fas fa-phone text-success fa-2x mb-2"></i>
                                <h6>Call Support</h6>
                                <small class="text-muted">+91-11-1234-5678</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center help-card">
                            <div class="card-body">
                                <i class="fas fa-envelope text-info fa-2x mb-2"></i>
                                <h6>Email Support</h6>
                                <small class="text-muted">help@veersahayata.gov.in</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center help-card">
                            <div class="card-body">
                                <i class="fas fa-comments text-warning fa-2x mb-2"></i>
                                <h6>Live Chat</h6>
                                <small class="text-muted">Available 9 AM - 6 PM</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center help-card">
                            <div class="card-body">
                                <i class="fas fa-video text-danger fa-2x mb-2"></i>
                                <h6>Video Help</h6>
                                <small class="text-muted">Tutorial Videos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <div class="card help-card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="helpTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                                    <i class="fas fa-list me-2"></i>Help Categories
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button" role="tab">
                                    <i class="fas fa-question me-2"></i>FAQ
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="guides-tab" data-bs-toggle="tab" data-bs-target="#guides" type="button" role="tab">
                                    <i class="fas fa-book me-2"></i>User Guides
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                    <i class="fas fa-headset me-2"></i>Contact Us
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-4" id="helpTabContent">
                            <!-- Help Categories -->
                            <div class="tab-pane fade show active" id="categories" role="tabpanel">
                                <div class="row">
                                    <?php foreach ($help_categories as $key => $category): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="category-card h-100" data-bs-toggle="collapse" data-bs-target="#<?php echo $key; ?>">
                                            <div class="card-body text-center">
                                                <i class="<?php echo $category['icon']; ?> category-icon text-<?php echo $category['color']; ?>"></i>
                                                <h5 class="card-title"><?php echo $category['title']; ?></h5>
                                                <p class="text-muted"><?php echo count($category['topics']); ?> topics</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Expandable Topics -->
                                        <div class="collapse mt-2" id="<?php echo $key; ?>">
                                            <?php foreach ($category['topics'] as $topic): ?>
                                            <div class="topic-card">
                                                <h6 class="mb-2"><?php echo $topic['title']; ?></h6>
                                                <p class="mb-0 text-muted small"><?php echo $topic['content']; ?></p>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- FAQ -->
                            <div class="tab-pane fade" id="faq" role="tabpanel">
                                <h4 class="mb-4">Frequently Asked Questions</h4>
                                <div class="accordion" id="faqAccordion">
                                    <?php foreach ($faqs as $index => $faq): ?>
                                    <div class="faq-item">
                                        <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $index; ?>">
                                            <i class="fas fa-chevron-right me-2"></i>
                                            <?php echo $faq['question']; ?>
                                        </button>
                                        <div id="faq<?php echo $index; ?>" class="collapse" data-bs-parent="#faqAccordion">
                                            <div class="faq-answer">
                                                <?php echo $faq['answer']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- User Guides -->
                            <div class="tab-pane fade" id="guides" role="tabpanel">
                                <h4 class="mb-4">User Guides & Tutorials</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card help-card">
                                            <div class="card-body">
                                                <i class="fas fa-video text-primary fa-2x mb-3"></i>
                                                <h5>Video Tutorials</h5>
                                                <p class="text-muted">Step-by-step video guides for common tasks</p>
                                                <button class="btn btn-primary btn-help">Watch Now</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card help-card">
                                            <div class="card-body">
                                                <i class="fas fa-file-pdf text-danger fa-2x mb-3"></i>
                                                <h5>PDF Guides</h5>
                                                <p class="text-muted">Downloadable guides for offline reference</p>
                                                <button class="btn btn-danger btn-help">Download</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card help-card">
                                            <div class="card-body">
                                                <i class="fas fa-mobile-alt text-success fa-2x mb-3"></i>
                                                <h5>Mobile App Guide</h5>
                                                <p class="text-muted">Using Veer Sahayata on mobile devices</p>
                                                <button class="btn btn-success btn-help">View Guide</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card help-card">
                                            <div class="card-body">
                                                <i class="fas fa-clipboard-list text-warning fa-2x mb-3"></i>
                                                <h5>Quick Reference</h5>
                                                <p class="text-muted">Cheat sheets for common procedures</p>
                                                <button class="btn btn-warning btn-help">View Cards</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Us -->
                            <div class="tab-pane fade" id="contact" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 class="mb-4">Get in Touch</h4>
                                        <form>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="contactName" class="form-label">Full Name</label>
                                                        <input type="text" class="form-control" id="contactName" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="contactEmail" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="contactEmail" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="contactSubject" class="form-label">Subject</label>
                                                <select class="form-select" id="contactSubject">
                                                    <option>General Inquiry</option>
                                                    <option>Technical Support</option>
                                                    <option>Pension Related</option>
                                                    <option>Document Issues</option>
                                                    <option>Verification Problems</option>
                                                    <option>Account Issues</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="contactMessage" class="form-label">Message</label>
                                                <textarea class="form-control" id="contactMessage" rows="5" placeholder="Describe your issue or question in detail..."></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-help">
                                                <i class="fas fa-paper-plane me-2"></i>Send Message
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="contact-card">
                                            <h5>Support Hours</h5>
                                            <p class="mb-3">
                                                <i class="fas fa-clock me-2"></i>
                                                Monday - Friday: 9:00 AM - 6:00 PM<br>
                                                Saturday: 9:00 AM - 2:00 PM<br>
                                                Sunday: Closed
                                            </p>
                                            
                                            <h6>Emergency Support</h6>
                                            <p class="mb-3">
                                                <i class="fas fa-phone me-2"></i>
                                                24/7 Emergency Line:<br>
                                                1800-VEER-HELP
                                            </p>
                                            
                                            <h6>Regional Offices</h6>
                                            <p class="mb-0">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                Find your nearest office<br>
                                                <a href="#" class="text-white">View Locations</a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('helpSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const categoryCards = document.querySelectorAll('.category-card');
            const topicCards = document.querySelectorAll('.topic-card');
            const faqItems = document.querySelectorAll('.faq-item');
            
            // Search in categories
            categoryCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
            
            // Search in topics
            topicCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
            
            // Search in FAQs
            faqItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // FAQ toggle icons
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const isExpanded = this.getAttribute('aria-expanded') === 'true';
                
                if (isExpanded) {
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-down');
                }
            });
        });

        // Category card interactions
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                const target = this.getAttribute('data-bs-target');
                const collapse = document.querySelector(target);
                
                if (collapse.classList.contains('show')) {
                    collapse.classList.remove('show');
                } else {
                    // Hide all other collapses
                    document.querySelectorAll('.collapse.show').forEach(c => {
                        c.classList.remove('show');
                    });
                    collapse.classList.add('show');
                }
            });
        });

        // Contact form submission
        document.querySelector('#contact form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const subject = document.getElementById('contactSubject').value;
            const message = document.getElementById('contactMessage').value;
            
            if (!message.trim()) {
                alert('Please enter your message.');
                return;
            }
            
            // Here you would typically send the form data to your server
            alert('Thank you for your message. Our support team will respond within 24 hours.');
            
            // Reset form
            document.getElementById('contactMessage').value = '';
        });
    </script>
</body>
</html>
