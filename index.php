<?php
session_start();

require_once './include/conn/conn.php';
// require_once './user/admin/dashboard.php';

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) 
{
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    if (!empty($username) && !empty($password) && in_array($role, ['student', 'teacher', 'admin'])) 
    {
        $query = "SELECT id, username, password, full_name, role FROM users WHERE username = '$username' AND role = '$role'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) 
        {
            $user = mysqli_fetch_assoc($result);
            if($password === $user['password']) 
            {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {
                    case 'admin':   header('Location: ./user/admin/dashboard.php'); exit;
                    case 'teacher': header('Location: ./user/teacher/dashboard.php'); exit;
                    case 'student': header('Location: ./user/student/dashboard.php'); exit;
                }
            } 
            else 
              $error = 'Invalid password.';
        } 
        else 
          $error = 'User not found with that role.';
    } 
    else 
      $error = 'Please fill all fields and select a valid role.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./include/css/index.css">
  <title>College Timetable · Login</title>
</head>
<body>
  <header>
    <h1> <div class="circle-shape"></div>
         <div class="square-shape"></div>
         <div class="triangle-shape"></div>
    COLLEGE TIMETABLE</h1>
    <nav>
      <a href="#" class="active">LOGIN</a>
      <a href="#">TIMETABLES</a>
      <a href="#">STATUS</a>
    </nav>
  </header>

  <section class="hero">
    <div class="login-box">
      <h3>ACCESS PORTAL</h3>

      <?php if ($error): ?>
        <div class="error-box"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="post" action="index.php">
        <div class="input-group">
          <label>👤 USERNAME / Email</label>
          <input type="text" name="username" placeholder="e.g. admin / teacher / stu_bca" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
        </div>
        <div class="input-group">
          <label>🔒 PASSWORD</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="role-selector">
          <div class="role-option">
            <input type="radio" name="role" id="roleStudent" value="student" <?php echo (!isset($_POST['role']) || $_POST['role'] == 'student') ? 'checked' : ''; ?>>
            <label for="roleStudent">STUDENT</label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="roleTeacher" value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'checked' : ''; ?>>
            <label for="roleTeacher">TEACHER</label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="roleAdmin" value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'checked' : ''; ?>>
            <label for="roleAdmin">ADMIN</label>
          </div>
        </div>

        <button class="login-btn" type="submit" name="login">LOGIN →</button>

        <!-- <div class="register-student">
          <a href="register.php">📝 Register as student</a>
        </div> -->
      </form>
    </div>
  </section>

</body>
</html>