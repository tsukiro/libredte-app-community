<?php $__view_layout .= '.min'; ?>
<?php $__view_title = 'Cambiar Contraseña'; ?>
<div class="container">
    <div class="text-center mt-4 mb-4">
        <a href="<?=$_base?>/"><img src="<?=$_base?>/img/logo.png" alt="Logo" class="img-fluid" style="max-width: 200px" /></a>
    </div>
    <div class="row">
        <div class="offset-md-3 col-md-6">
            <?=\sowerphp\core\Facade_Session_Message::getMessagesAsString()?>
            <div class="card">
                <div class="card-body">
                    <h1 class="text-center mb-4">Reiniciar contraseña</h1>
                    <form action="<?=$_base.$_request?>" method="post" onsubmit="return Form.check()" class="mb-4" id="recuperarForm">
                        <div class="form-group">
                            <label for="pass1" class="sr-only">Contraseña</label>
                            <input type="password" name="contrasenia1" id="pass1" class="form-control form-control-lg" required="required" placeholder="Nueva contraseña">
                        </div>
                        <div class="form-group">
                            <label for="pass2" class="sr-only">Contraseña</label>
                            <input type="password" name="contrasenia2" id="pass2" class="form-control form-control-lg" required="required" placeholder="Repetir contraseña">
                        </div>
                        <input type="hidden" name="codigo" value="<?=$codigo?>" />
                        <?=\sowerphp\general\Utility_Google_Recaptcha::form('recuperarForm')?>
                        <button type="submit" class="btn btn-primary btn-lg col-12">Cambiar contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script> $(function() { $("#pass1").focus(); }); </script>
</div>
