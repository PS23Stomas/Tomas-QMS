document.addEventListener('DOMContentLoaded', function() {
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var sidebarClose = document.getElementById('sidebarClose');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('open');
        });
    }

    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('open');
        });
    }

    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('open');
            }
        }
    });
});

function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

function confirmDelete(url, name) {
    if (confirm('Ar tikrai norite ištrinti: ' + name + '?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
