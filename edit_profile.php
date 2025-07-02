<?php
session_start();
require_once('../includes/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT u.*, pp.* FROM users u 
                        LEFT JOIN partner_preferences pp ON u.user_id = pp.user_id 
                        WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        
        // Update user table
        $update_user = "UPDATE users SET 
            first_name = ?, last_name = ?, phone = ?, religion = ?, caste = ?, 
            marital_status = ?, address = ?, city = ?, province = ?, education = ?, 
            profession = ?, income_range = ?, about_me = ? 
            WHERE user_id = ?";
        
        $stmt = $conn->prepare($update_user);
        $stmt->bind_param("sssssssssssssi", 
            $_POST['first_name'], $_POST['last_name'], $_POST['phone'], $_POST['religion'],
            $_POST['caste'], $_POST['marital_status'], $_POST['address'], $_POST['city'],
            $_POST['province'], $_POST['education'], $_POST['profession'], 
            $_POST['income_range'], $_POST['about_me'], $user_id
        );
        $stmt->execute();
        
        // Update or insert partner preferences
        $check_pref = $conn->prepare("SELECT user_id FROM partner_preferences WHERE user_id = ?");
        $check_pref->bind_param("i", $user_id);
        $check_pref->execute();
        $pref_exists = $check_pref->get_result()->num_rows > 0;
        
        if ($pref_exists) {
            $update_pref = "UPDATE partner_preferences SET 
                min_age = ?, max_age = ?, pref_religion = ?, pref_caste = ?, 
                pref_marital_status = ?, pref_profession = ?, pref_location = ?, 
                pref_education = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_pref);
        } else {
            $insert_pref = "INSERT INTO partner_preferences 
                (user_id, min_age, max_age, pref_religion, pref_caste, pref_marital_status, 
                pref_profession, pref_location, pref_education) VALUES (?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($insert_pref);
        }
        
        $stmt->bind_param("iissssssi", 
            $pref_exists ? null : $user_id,
            $_POST['min_age'], $_POST['max_age'], $_POST['pref_religion'], $_POST['pref_caste'],
            $_POST['pref_marital_status'], $_POST['pref_profession'], $_POST['pref_location'],
            $_POST['pref_education'], $pref_exists ? $user_id : null
        );
        
        if (!$pref_exists) {
            $stmt->bind_param("iisssssssi", 
                $user_id, $_POST['min_age'], $_POST['max_age'], $_POST['pref_religion'], 
                $_POST['pref_caste'], $_POST['pref_marital_status'], $_POST['pref_profession'], 
                $_POST['pref_location'], $_POST['pref_education']
            );
        }
        
        $stmt->execute();
        $conn->commit();
        
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT u.*, pp.* FROM users u 
                                LEFT JOIN partner_preferences pp ON u.user_id = pp.user_id 
                                WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Helakapuwa.com</title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #0096C7;
            --secondary-blue: #0077A3;
            --light-blue: #48CAE4;
            --accent-gold: #FFD60A;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
            --bg-light: #F8FAFC;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
        }

        .sidebar {
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border-right: 1px solid #E5E7EB;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            color: var(--text-gray);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            background: #F3F4F6;
            color: var(--primary-blue);
            border-left-color: var(--primary-blue);
        }

        .form-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
        }

        .section-title {
            color: var(--primary-blue);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #E5E7EB;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #E5E7EB;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #FAFAFA;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 150, 199, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 150, 199, 0.4);
        }

        .btn-secondary {
            background: #F3F4F6;
            color: var(--text-dark);
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            border: 2px solid #E5E7EB;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .success-message {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .photo-upload {
            border: 2px dashed #E5E7EB;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #FAFAFA;
        }

        .photo-upload:hover {
            border-color: var(--primary-blue);
            background: rgba(0, 150, 199, 0.02);
        }

        .profile-photo-current {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--light-blue), var(--primary-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin: 0 auto 1rem;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0, 150, 199, 0.3);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        .mobile-menu-toggle {
            display: none;
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-heart text-white"></i>
                </div>
                <span class="text-xl font-bold text-gray-800">Helakapuwa</span>
            </div>
        </div>

        <!-- User Profile Summary -->
        <div class="p-6 border-b border-gray-200">
            <div class="text-center">
                <div class="profile-photo-current">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                </div>
                <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['profession'] ?? 'Professional'); ?></p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="py-4">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item active">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="my_requests.php" class="nav-item">
                <i class="fas fa-heart"></i>
                <span>Matches</span>
            </a>
            <a href="chat.php" class="nav-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>
            <a href="my_connections.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Connections</span>
            </a>
            <a href="../api/logout.php" class="nav-item text-red-600 hover:text-red-700">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Profile</h1>
                    <p class="text-gray-600">Update your information to get better matches</p>
                </div>
                <a href="dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="p-6">
            <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Religion</label>
                            <select name="religion" class="form-input">
                                <option value="">Select Religion</option>
                                <option value="Buddhist" <?php echo ($user['religion'] ?? '') === 'Buddhist' ? 'selected' : ''; ?>>Buddhist</option>
                                <option value="Hindu" <?php echo ($user['religion'] ?? '') === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                <option value="Christian" <?php echo ($user['religion'] ?? '') === 'Christian' ? 'selected' : ''; ?>>Christian</option>
                                <option value="Islam" <?php echo ($user['religion'] ?? '') === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                <option value="Other" <?php echo ($user['religion'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Caste</label>
                            <input type="text" name="caste" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['caste'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Marital Status</label>
                            <select name="marital_status" class="form-input">
                                <option value="">Select Status</option>
                                <option value="Single" <?php echo ($user['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Divorced" <?php echo ($user['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($user['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Province</label>
                            <select name="province" class="form-input">
                                <option value="">Select Province</option>
                                <option value="Western" <?php echo ($user['province'] ?? '') === 'Western' ? 'selected' : ''; ?>>Western Province</option>
                                <option value="Central" <?php echo ($user['province'] ?? '') === 'Central' ? 'selected' : ''; ?>>Central Province</option>
                                <option value="Southern" <?php echo ($user['province'] ?? '') === 'Southern' ? 'selected' : ''; ?>>Southern Province</option>
                                <option value="Northern" <?php echo ($user['province'] ?? '') === 'Northern' ? 'selected' : ''; ?>>Northern Province</option>
                                <option value="Eastern" <?php echo ($user['province'] ?? '') === 'Eastern' ? 'selected' : ''; ?>>Eastern Province</option>
                                <option value="North Western" <?php echo ($user['province'] ?? '') === 'North Western' ? 'selected' : ''; ?>>North Western Province</option>
                                <option value="North Central" <?php echo ($user['province'] ?? '') === 'North Central' ? 'selected' : ''; ?>>North Central Province</option>
                                <option value="Uva" <?php echo ($user['province'] ?? '') === 'Uva' ? 'selected' : ''; ?>>Uva Province</option>
                                <option value="Sabaragamuwa" <?php echo ($user['province'] ?? '') === 'Sabaragamuwa' ? 'selected' : ''; ?>>Sabaragamuwa Province</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-input h-24 resize-none"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-briefcase"></i>
                        Professional Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Education</label>
                            <select name="education" class="form-input">
                                <option value="">Select Education</option>
                                <option value="High School" <?php echo ($user['education'] ?? '') === 'High School' ? 'selected' : ''; ?>>High School</option>
                                <option value="Diploma" <?php echo ($user['education'] ?? '') === 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                                <option value="Bachelor's Degree" <?php echo ($user['education'] ?? '') === "Bachelor's Degree" ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                <option value="Master's Degree" <?php echo ($user['education'] ?? '') === "Master's Degree" ? 'selected' : ''; ?>>Master's Degree</option>
                                <option value="PhD" <?php echo ($user['education'] ?? '') === 'PhD' ? 'selected' : ''; ?>>PhD</option>
                                <option value="Other" <?php echo ($user['education'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Profession</label>
                            <input type="text" name="profession" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['profession'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Income Range</label>
                            <select name="income_range" class="form-input">
                                <option value="">Select Income Range</option>
                                <option value="Below 50k" <?php echo ($user['income_range'] ?? '') === 'Below 50k' ? 'selected' : ''; ?>>Below LKR 50,000</option>
                                <option value="50k-100k" <?php echo ($user['income_range'] ?? '') === '50k-100k' ? 'selected' : ''; ?>>LKR 50,000 - 100,000</option>
                                <option value="100k-200k" <?php echo ($user['income_range'] ?? '') === '100k-200k' ? 'selected' : ''; ?>>LKR 100,000 - 200,000</option>
                                <option value="200k-500k" <?php echo ($user['income_range'] ?? '') === '200k-500k' ? 'selected' : ''; ?>>LKR 200,000 - 500,000</option>
                                <option value="500k+" <?php echo ($user['income_range'] ?? '') === '500k+' ? 'selected' : ''; ?>>Above LKR 500,000</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- About Me -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-heart"></i>
                        About Me
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label">Tell us about yourself</label>
                        <textarea name="about_me" class="form-input h-32 resize-none" 
                                  placeholder="Describe yourself, your interests, and what you're looking for in a partner..."><?php echo htmlspecialchars($user['about_me'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Partner Preferences -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-search"></i>
                        Partner Preferences
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Preferred Age Range</label>
                            <div class="grid grid-cols-2 gap-3">
                                <input type="number" name="min_age" class="form-input" placeholder="Min Age" 
                                       value="<?php echo htmlspecialchars($user['min_age'] ?? ''); ?>" min="18" max="80">
                                <input type="number" name="max_age" class="form-input" placeholder="Max Age" 
                                       value="<?php echo htmlspecialchars($user['max_age'] ?? ''); ?>" min="18" max="80">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Religion</label>
                            <select name="pref_religion" class="form-input">
                                <option value="">Any Religion</option>
                                <option value="Buddhist" <?php echo ($user['pref_religion'] ?? '') === 'Buddhist' ? 'selected' : ''; ?>>Buddhist</option>
                                <option value="Hindu" <?php echo ($user['pref_religion'] ?? '') === 'Hindu' ? 'selected' : ''; ?>>Hindu</option>
                                <option value="Christian" <?php echo ($user['pref_religion'] ?? '') === 'Christian' ? 'selected' : ''; ?>>Christian</option>
                                <option value="Islam" <?php echo ($user['pref_religion'] ?? '') === 'Islam' ? 'selected' : ''; ?>>Islam</option>
                                <option value="Other" <?php echo ($user['pref_religion'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Caste</label>
                            <input type="text" name="pref_caste" class="form-input" placeholder="Any Caste" 
                                   value="<?php echo htmlspecialchars($user['pref_caste'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Marital Status</label>
                            <select name="pref_marital_status" class="form-input">
                                <option value="">Any Status</option>
                                <option value="Single" <?php echo ($user['pref_marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Divorced" <?php echo ($user['pref_marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($user['pref_marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Profession</label>
                            <input type="text" name="pref_profession" class="form-input" placeholder="Any Profession" 
                                   value="<?php echo htmlspecialchars($user['pref_profession'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Location</label>
                            <input type="text" name="pref_location" class="form-input" placeholder="Any Location" 
                                   value="<?php echo htmlspecialchars($user['pref_location'] ?? ''); ?>">
                        </div>

                        <div class="form-group md:col-span-2">
                            <label class="form-label">Preferred Education</label>
                            <input type="text" name="pref_education" class="form-input" placeholder="Any Education Level" 
                                   value="<?php echo htmlspecialchars($user['pref_education'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Profile Photo -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-camera"></i>
                        Profile Photo
                    </h2>
                    
                    <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600 mb-2">Click to upload your profile photo</p>
                        <p class="text-sm text-gray-500">Supported formats: JPG, PNG (Max: 5MB)</p>
                        <input type="file" name="profile_photo" accept="image/*" class="hidden" id="photoInput">
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-4 justify-end">
                    <a href="dashboard.php" class="btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('mobile-open');
            
            if (window.innerWidth <= 768) {
                document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : 'auto';
            }
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('mobile-open');
                document.body.style.overflow = 'auto';
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('mobile-open') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                toggleSidebar();
            }
        });

        // Photo upload preview
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // File validation
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    this.value = '';
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file');
                    this.value = '';
                    return;
                }
                
                // Show file name
                const uploadArea = document.querySelector('.photo-upload');
                uploadArea.innerHTML = `
                    <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                    <p class="text-green-600 mb-2">Photo selected: ${file.name}</p>
                    <p class="text-sm text-gray-500">Click to change photo</p>
                `;
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            
            if (!firstName || !lastName) {
                e.preventDefault();
                alert('Please fill in your first and last name');
                return;
            }
            
            // Age range validation
            const minAge = parseInt(document.querySelector('input[name="min_age"]').value);
            const maxAge = parseInt(document.querySelector('input[name="max_age"]').value);
            
            if (minAge && maxAge && minAge > maxAge) {
                e.preventDefault();
                alert('Minimum age cannot be greater than maximum age');
                return;
            }
        });

        console.log('Edit Profile page loaded successfully!');
    </script>
</body>
</html>
