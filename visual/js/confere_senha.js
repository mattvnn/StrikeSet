function conferirSenhas(formId) {
    var form = document.getElementById(formId);
    if (!form) return;

    var senha   = form.querySelector('#senha-cadastro');
    var repetir = form.querySelector('#senha-repetir');
    var dica    = form.querySelector('#senha-hint');

    function conferir() {
        if (repetir.value === '') {
            repetir.setCustomValidity('');
            repetir.classList.remove('campo-erro', 'campo-ok');
            dica.textContent = '';
            dica.className = 'field-hint';
            return;
        }

        if (senha.value !== repetir.value) {
            repetir.setCustomValidity('As senhas não coincidem.');
            repetir.classList.add('campo-erro');
            repetir.classList.remove('campo-ok');
            dica.textContent = 'As senhas não coincidem.';
            dica.className = 'field-hint field-hint--erro';
        } else {
            repetir.setCustomValidity('');
            repetir.classList.add('campo-ok');
            repetir.classList.remove('campo-erro');
            dica.textContent = 'As senhas coincidem.';
            dica.className = 'field-hint field-hint--ok';
        }
    }

    senha.addEventListener('input', conferir);
    repetir.addEventListener('input', conferir);

    form.addEventListener('submit', function (e) {
        conferir();
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
        }
    });
}
