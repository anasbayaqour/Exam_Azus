<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}
require_once 'config.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch all accepted students with their filiere and group names
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.email, 
               f.name AS filiere_name, g.name AS group_name 
        FROM users u
        LEFT JOIN filieres f ON u.filiere_id = f.id
        LEFT JOIN student_groups g ON u.group_id = g.id
        WHERE u.status = 'accepted' AND u.user_type = 'student'";
$stmt = $pdo->query($sql);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard 2.0</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">
    <style>
        .custom-gradient {
            background: linear-gradient(135deg, rgb(8, 43, 71) 0%, rgb(14, 13, 99) 100%);
        }
        
        .card-gradient {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8));
            backdrop-filter: blur(10px);
        }
        
        .student-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .student-card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .action-button {
            transition: all 0.3s ease;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
        }
        
        .hover-trigger .hover-target {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        
        .hover-trigger:hover .hover-target {
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes fadeScale {
            0% { opacity: 0; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .initial-animation {
            animation: fadeScale 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
    </style>
</head>
<body class="custom-gradient min-h-screen">
    <div class="container mx-auto p-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-6xl font-extrabold text-white mb-4 initial-animation">
                Student Dashboard
            </h1>
            <p class="text-white text-opacity-90 text-xl initial-animation" style="animation-delay: 0.2s">
                Manage and monitor student progress
            </p>
        </div>

        <!-- Routeur Button -->
        <div class="flex justify-center mb-8">
            <a href="teacher.php" 
               class="px-8 py-3 bg-white bg-opacity-20 text-white rounded-2xl hover:bg-opacity-30 transition-all duration-300 
                      flex items-center space-x-2 shadow-lg hover:shadow-xl">
                <i class="fas fa-arrow-left"></i>
                <span>Routeur</span>
            </a>
        </div>

        <!-- Search Bar -->
        <div class="max-w-xl mx-auto mb-12 initial-animation" style="animation-delay: 0.3s">
            <div class="relative">
                <input type="text" 
                       id="searchInput"
                       placeholder="Search students..." 
                       class="w-full px-8 py-4 rounded-2xl bg-white bg-opacity-20 border border-white border-opacity-20 
                              text-white placeholder-white placeholder-opacity-70 focus:outline-none focus:ring-4 
                              focus:ring-white focus:ring-opacity-30 transition-all duration-300">
                <i class="fas fa-search absolute right-6 top-1/2 transform -translate-y-1/2 text-white text-opacity-70"></i>
            </div>
        </div>

        <!-- Students Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8" id="studentsContainer">
            <?php foreach ($students as $index => $student): ?>
                <?php
                $delay = ($index % 9) * 0.1;
                ?>
                <div class="student-card card-gradient rounded-3xl overflow-hidden shadow-xl initial-animation hover-trigger"
                     data-student-id="<?php echo $student['user_id']; ?>"
                     style="animation-delay: <?php echo 0.4 + $delay; ?>s">
                    <div class="p-8">
                        <div class="flex items-start justify-between mb-6">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 rounded-2xl custom-gradient flex items-center justify-center 
                                            text-white text-2xl font-bold shadow-lg">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-800 mb-1">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </h3>
                                    <p class="text-indigo-600 font-medium">
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-3">
                                <span class="px-4 py-2 rounded-xl bg-indigo-100 text-indigo-600 font-medium text-sm">
                                    <?php echo htmlspecialchars($student['filiere_name'] ?? 'Not specified'); ?>
                                </span>
                                <span class="px-4 py-2 rounded-xl bg-purple-100 text-purple-600 font-medium text-sm">
                                    Group <?php echo htmlspecialchars($student['group_name'] ?? 'Not assigned'); ?>
                                </span>
                            </div>

                            <div class="flex justify-end space-x-3 mt-6 hover-target">
                                <button onclick="viewResults(<?php echo $student['user_id']; ?>)" 
                                        class="action-button p-3 rounded-xl bg-blue-500 text-white hover:bg-blue-600">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <button onclick="editStudent(<?php echo $student['user_id']; ?>)" 
                                        class="action-button p-3 rounded-xl bg-amber-500 text-white hover:bg-amber-600">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteStudent(<?php echo $student['user_id']; ?>)" 
                                        class="action-button p-3 rounded-xl bg-red-500 text-white hover:bg-red-600">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl max-w-md w-full mx-4 transform transition-all duration-300 modal-animation" id="editModalContent">
            <div class="p-6">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Edit Student</h2>
                <form id="editForm" class="space-y-4">
                    <input type="hidden" id="editStudentId" name="user_id">
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">First Name</label>
                        <input type="text" id="editFirstName" name="first_name" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Last Name</label>
                        <input type="text" id="editLastName" name="last_name" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Email</label>
                        <input type="email" id="editEmail" name="email" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Filière</label>
                        <input type="text" id="editFiliere" name="filiere_name" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Group</label>
                        <input type="text" id="editGroup" name="group_name" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300">
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-6 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-300 font-medium">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-all duration-300 font-medium">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Search Functionality with Animation
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('#studentsContainer > div');

            cards.forEach(card => {
                const name = card.querySelector('h3').textContent.toLowerCase();
                const email = card.querySelector('p').textContent.toLowerCase();
                const group = card.querySelector('span:nth-child(2)').textContent.toLowerCase();

                if (name.includes(searchTerm) || email.includes(searchTerm) || group.includes(searchTerm)) {
                    card.style.display = '';
                    card.classList.add('card-animation');
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // View Results with Enhanced Animation
        function viewResults(studentId) {
            fetch(`get_results.php?student_id=${studentId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('resultsContent').innerHTML = `
                        <div class="space-y-4">
                            ${data.results.map((result, index) => `
                                <div class="bg-gray-50 p-4 rounded-lg transform transition-all duration-300 hover:scale-102 hover:bg-gray-100"
                                     style="animation: slideIn 0.3s ease-out ${index * 0.1}s both">
                                    <div class="font-semibold text-lg">${result.subject}</div>
                                    <div class="text-gray-600 mt-2">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" 
                                                 style="width: ${result.grade}%"></div>
                                        </div>
                                        <div class="mt-1">Grade: ${result.grade}%</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    document.getElementById('resultsModal').classList.remove('hidden');
                });
        }

        function closeResultsModal() {
            const modal = document.getElementById('resultsModal');
            modal.classList.add('animate__fadeOut');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('animate__fadeOut');
            }, 300);
        }

        // Enhanced Edit Student Function
        function editStudent(studentId) {
            fetch(`get_student.php?id=${studentId}`)
                .then(response => response.json())
                .then(student => {
                    document.getElementById('editStudentId').value = student.user_id;
                    document.getElementById('editFirstName').value = student.first_name;
                    document.getElementById('editLastName').value = student.last_name;
                    document.getElementById('editEmail').value = student.email;
                    document.getElementById('editFiliere').value = student.filiere_name;
                    document.getElementById('editGroup').value = student.group_name;
                    
                    // Show modal with animation
                    const modal = document.getElementById('editModal');
                    const modalContent = document.getElementById('editModalContent');
                    
                    modal.classList.remove('hidden');
                    modalContent.classList.add('modal-animation');
                });
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            const modalContent = document.getElementById('editModalContent');
            
            modalContent.classList.remove('modal-animation');
            modalContent.classList.add('animate__fadeOut');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modalContent.classList.remove('animate__fadeOut');
            }, 300);
        }

        // Enhanced Form Submission with Animation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Add loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Saving...
            `;
            
            fetch('update_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successMessage = document.createElement('div');
                    successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-500 translate-y-0';
                    successMessage.textContent = 'Student updated successfully!';
                    document.body.appendChild(successMessage);
                    
                    // Remove success message after 3 seconds
                    setTimeout(() => {
                        successMessage.style.transform = 'translateY(-100%)';
                        setTimeout(() => successMessage.remove(), 500);
                    }, 3000);
                    
                    // Reload page with fade effect
                    document.body.style.opacity = '0';
                    setTimeout(() => location.reload(), 500);
                } else {
                    // Show error message
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-500 translate-y-0';
                    errorMessage.textContent = 'Error updating student. Please try again.';
                    document.body.appendChild(errorMessage);
                    
                    setTimeout(() => {
                        errorMessage.style.transform = 'translateY(-100%)';
                        setTimeout(() => errorMessage.remove(), 500);
                    }, 3000);
                }
            })
            .finally(() => {
                // Reset button state
                submitButton.disabled = false;
                submitButton.innerHTML = 'Save Changes';
            });
        });

        // Enhanced Delete Function with Confirmation Modal
        function deleteStudent(studentId) {
            // Create and show confirmation modal
            const confirmModal = document.createElement('div');
            confirmModal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
            confirmModal.innerHTML = `
                <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4 transform transition-all duration-300 modal-animation">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Confirm Deletion</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete this student? This action cannot be undone.</p>
                    <div class="flex justify-end gap-3">
                        <button onclick="this                                .closest('.fixed').remove()" 
                                class="px-6 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-all duration-300 font-medium">
                            Cancel
                        </button>
                        <button onclick="confirmDelete(<?php echo $student['user_id']; ?>)" 
                                class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-all duration-300 font-medium">
                            Delete
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmModal);
        }

        function confirmDelete(studentId) {
            fetch('delete_student.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ user_id: studentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find and remove the student card with animation
                    const studentCard = document.querySelector(`[data-student-id="${studentId}"]`);
                    studentCard.style.transform = 'scale(0.8)';
                    studentCard.style.opacity = '0';
                    
                    setTimeout(() => {
                        studentCard.remove();
                        // Show success message
                        const successMessage = document.createElement('div');
                        successMessage.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg transform transition-all duration-500 translate-y-0';
                        successMessage.textContent = 'Student deleted successfully!';
                        document.body.appendChild(successMessage);
                        
                        setTimeout(() => {
                            successMessage.style.transform = 'translateY(-100%)';
                            setTimeout(() => successMessage.remove(), 500);
                        }, 3000);
                    }, 300);
                }
            });
            
            // Remove confirmation modal
            document.querySelector('.fixed').remove();
        }

        // Add Loading Animation
        window.addEventListener('load', () => {
            document.body.classList.add('loaded');
            const cards = document.querySelectorAll('.student-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('opacity-100', 'translate-y-0');
                }, index * 100);
            });
        });
    </script>
</body>
</html>