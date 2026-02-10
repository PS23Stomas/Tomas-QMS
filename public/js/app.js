// Kliento pusės JavaScript - šoninės juostos valdymas, modaliniai langai, ištrynimo patvirtinimas

// Kai DOM pilnai užkrautas, inicializuojami įvykių klausytojai
document.addEventListener('DOMContentLoaded', function() {
    // Elementų paieška: meniu mygtukas, šoninė juosta, uždarymo mygtukas
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var sidebarClose = document.getElementById('sidebarClose');

    // Šoninės juostos atidarymas paspaudus meniu mygtuką
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('open');
        });
    }

    // Šoninės juostos uždarymas paspaudus uždarymo mygtuką
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('open');
        });
    }

    // Šoninės juostos uždarymas paspaudus bet kur už jos ribų
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('open');
            }
        }
    });
});

/**
 * Atidaro modalinį langą pagal jo ID
 * @param {string} id - Modalinio lango elemento identifikatorius
 */
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

/**
 * Uždaro modalinį langą pagal jo ID
 * @param {string} id - Modalinio lango elemento identifikatorius
 */
function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

/**
 * Ištrynimo patvirtinimo funkcija - rodo patvirtinimo dialogą ir
 * siunčia POST užklausą su 'delete' veiksmu, jei vartotojas patvirtina
 * @param {string} url - Veiksmo URL, į kurį siunčiama ištrynimo užklausa
 * @param {string} name - Elemento pavadinimas, rodomas patvirtinimo dialoge
 */
function confirmDelete(url, name) {
    if (confirm('Ar tikrai norite ištrinti: ' + name + '?')) {
        // Dinamiškai sukuriama ir pateikiama forma su POST metodu
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
