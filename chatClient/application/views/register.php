<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Register</title>
        <link rel="stylesheet" href="<?php echo base_url("assets/register.css")?>">
</head>
<body>

  <div class="container">
    <div class="register-card">
      <h2>Create Account</h2>

      <form action="<?= site_url("/chatController/registerSubmit")?>" method="POST">
        <div class="input-group">
          <input type="text" placeholder="username" name="username" required />
        </div>

        <div class="input-group">
          <input type="password" placeholder="Password" name="password" required />
        </div>

        <p>already have an account ? <a href="<?=site_url("chatcontroller/login");?>">login</a></p>
        <button type="submit">Register</button>
        <?php if(isset($error)){ ?>
            <p><?=$error?></p>
        <?php } ?>
      </form>

    </div>
  </div>

</body>
</html>
