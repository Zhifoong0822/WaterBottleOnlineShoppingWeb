<!-- Member Details Page -->

<?php
// Core Components
require_once '_base.php';
$_title = 'Member Registration';
include '_head.php';

?>

<?php
// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['checkEmail'])) {
    $username = trim(req('usernameTxt', ''));
    $email = trim(req('emailTxt', ''));
    $password = req('passwordTxt', '');

    // Hash password
    $hashPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stm = $_db->prepare("
        INSERT INTO users
        (
            username,
            email,
            password
        )
        VALUES
        (?, ?, ?)
    ");

    $stm->execute([
        $username,
        $email,
        $hashPassword
    ]);

    // Get created user ID
    $userID = $_db->lastInsertId();

    // Save profile image
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $extension = pathinfo(
            $_FILES['image']['name'],
            PATHINFO_EXTENSION
        );

        // Final image name
        $imageName = $userID . "." . $extension;
        $imagePath = "img/member/" . $imageName;

        move_uploaded_file(
            $_FILES['image']['tmp_name'],
            $imagePath
        );
    }
    else {
        // Use default profile image
        $imageName = $userID . ".png";
        $imagePath = "img/member/" . $imageName;

        copy(
            "img/defaultProfile.png",
            $imagePath
        );
    }

    // Redirect after success
    redirect('/');
    exit;
}
?>

<?php
// Check email exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkEmail'])) {
    header('Content-Type: application/json');

    $email = trim(req('email', ''));

    $stm = $_db->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE email = ?
    ");
    $stm->execute([$email]);

    echo json_encode([
        'exists' => $stm->fetchColumn() > 0
    ]);

    exit;
}
?>

<link rel="stylesheet" href="css/admin.css">
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/login.css">

<div class="admin-container" style="width: 42%;">
    <div class="view-card">
        <form 
            id="memberForm" 
            method="post" 
            enctype="multipart/form-data" 
            style="display: flex; flex-direction: column; align-items: center;"
        >
            <!-- Profile Image -->
            <div class="view-image" style="margin-bottom:15px;">
                <img
                    id="profilePreview"
                    src="img/defaultProfile.png"
                    alt="Member Profile"
                    class="view-product-image"
                    style="cursor:pointer;"
                >

                <input
                    id="profileImg"
                    type="file"
                    name="image"
                    accept="image/*"
                    hidden
                >

                <small id="imageError" class="error"></small>
            </div>

            <!-- Details -->
            <div class="edit-content" style="gap: 15px;">
                <div class="input-pill">
                    <label>Username</label>
                    <input
                        id="usernameTxt"
                        name="usernameTxt"
                        type="text"
                    >
                    <small id="usernameError" class="error">
                    </small>
                </div>
                <div class="form-group"></div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input
                        id="emailTxt"
                        name="emailTxt"
                        type="email"
                    >
                    <small id="emailError" class="error">
                    </small>
                </div>
                <div class="form-group"></div>

                <div class="form-group">
                    <label>Password</label>
                    <input
                        id="passwordTxt"
                        name="passwordTxt"
                        type="password"
                    >
                    <small id="passwordError" class="error">
                    </small>
                </div>
                <div class="form-group"></div>
            </div>

            <button type="submit" class="btn-view" style="margin-top: 20px; padding: 10px 28px; font-size: medium;">
                Register
            </button>
        </form>
    </div>
</div>

<?php include '_foot.php'; ?>

<script>
$(function(){
    let emailAvailable = null;

    // Profile upload
    $('#profilePreview').click(function(){
        $('#profileImg').click();
    });

    // Set preview image
    $('#profileImg').change(function(){
        let file = this.files[0];

        if(file){
            let reader = new FileReader();
            reader.onload = function(e){
                $('#profilePreview')
                    .attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }

    });

    // Email checking
    $('#emailTxt').on('blur', function(){
        let email = $(this).val().trim();

        $('#emailError').text('');
        emailAvailable = null;

        if(email === '')
            return;

        $.post('',{
            checkEmail:true,
            email:email
        },function(result){
            if(result.exists){
                $('#emailError').text('Email already exists');
                $('#emailTxt').focus();

                emailAvailable = false;
            }
            else{
                emailAvailable = true;
            }

        },'json');

    });

    // Form validation
    $('#memberForm').submit(function(e){
        $('#usernameError').text('');
        $('#emailError').text('');
        $('#passwordError').text('');


        let name = $('#usernameTxt').val().trim();
        let email = $('#emailTxt').val().trim();
        let password = $('#passwordTxt').val();

        if(name === ''){
            $('#usernameError').text('Username is required');
            $('#usernameTxt').focus();
            return e.preventDefault();
        }

        if(email === ''){
            $('#emailError').text('Email is required');
            $('#emailTxt').focus();
            return e.preventDefault();
        }
        else if(!email.includes('@')){
            $('#emailError').text('Invalid email');
            $('#emailTxt').focus();
            return e.preventDefault();
        }
        else if(emailAvailable === false){
            $('#emailError').text('Email already exists');
            $('#emailTxt').focus();
            return e.preventDefault();
        }

        if(password === ''){
            $('#passwordError').text('Password is required');
            $('#passwordTxt').focus();
            return e.preventDefault();
        }
        else if(password.length < 6){
            $('#passwordError').text('Password must be at least 6 characters');
            $('#passwordTxt').focus();
            return e.preventDefault();
        }
    });


});

</script>