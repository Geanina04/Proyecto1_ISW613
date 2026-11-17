// JavaScript mínimo: solo comodidad de uso. Toda validación real ocurre en el servidor.
document.addEventListener('DOMContentLoaded', function () {
    // Pedir confirmación en formularios con data-confirmar
    document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm(f.dataset.confirmar)) ev.preventDefault();
        });
    });
    // Enviar automáticamente el selector de registros por página
    document.querySelectorAll('select.auto-submit').forEach(function (s) {
        s.addEventListener('change', function () { s.form.submit(); });
    });
    // Mostrar/ocultar contraseña según el rol en el formulario de usuarios
    var rol = document.getElementById('rol_id');
    var bloque = document.getElementById('bloque-password');
    if (rol && bloque) {
        var actualizar = function () {
            var opcion = rol.options[rol.selectedIndex];
            bloque.hidden = opcion && opcion.dataset.rol === 'profesor';
        };
        rol.addEventListener('change', actualizar);
        actualizar();
    }
});

// Filtro rápido de equipos en el formulario de préstamo (solo visual)
document.addEventListener('DOMContentLoaded', function () {
    var filtro = document.getElementById('filtro-equipos');
    if (!filtro) return;
    filtro.addEventListener('input', function () {
        var texto = filtro.value.trim().toLowerCase();
        document.querySelectorAll('#lista-equipos label').forEach(function (l) {
            l.hidden = texto !== '' && l.dataset.texto.indexOf(texto) === -1;
        });
    });
});
