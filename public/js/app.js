/**
 * app.js — Pagrindinis kliento JavaScript failas
 *
 * Šis failas įkeliamas kiekviename puslapyje (per header.php) ir atsakingas
 * už tris pagrindines funkcijas:
 *
 *   1. SESIJOS LAIKMAITIS — stebi vartotojo neaktyvumą ir perspėja prieš
 *      automatinį atsijungimą. Jei vartotojas 30 minučių nieko nedaro —
 *      nukreipiamas į prisijungimo puslapį.
 *
 *   2. ŠONINĖ JUOSTA — valdo šoninės navigacijos atidarymą / uždarymą
 *      mobiliuose įrenginiuose (mygtukas "hamburger").
 *
 *   3. MODALINIAI LANGAI — atidaro ir uždaro iššokančius langus (modals),
 *      tvarko klaviatūros navigaciją juose (Tab, Escape), grąžina fokusą
 *      po uždarymo.
 *
 * Technologija: vanilla JavaScript (be jokių framework'ų ar bibliotekų).
 * Palaikomi naršyklės įvykiai: DOMContentLoaded, keydown, click, scroll ir kt.
 */

/* =========================================================================
   1. SESIJOS LAIKMAITIS
   Stebi vartotojo neaktyvumą ir automatiškai atjungia po 30 minučių.
   Veikia visur išskyrus prisijungimo ir slaptažodžio puslapiuose.
   ========================================================================= */
(function() {
    /* Sesijos trukmė: 30 minučių (milisekundėmis).
       Turi atitikti PHP pusės nustatymą: session.gc_maxlifetime = 1800 sek. */
    var SESIJOS_LAIKAS = 30 * 60 * 1000;

    /* Perspėjimo laikas: 2 minutės iki sesijos pabaigos parodomas įspėjimas.
       Pvz.: po 28 min neaktyvumo rodomas geltonas juostelė viršuje. */
    var ISPEJIMO_LAIKAS = 2 * 60 * 1000;

    /* Laikmačių nuorodos — reikalingos norint juos atšaukti, kai vartotojas
       vėl pradeda veikti (pvz. pajudina pelę). */
    var sesijosTimer = null;
    var ispejimoTimer = null;

    /* Įspėjimo elemento nuoroda — saugoma, kad nereikėtų jo ieškoti DOM kaskart. */
    var ispejimoElementas = null;

    /**
     * Iš naujo paleidžia abu laikmačius (perspėjimo ir atsijungimo).
     * Kviečiama kaskart, kai vartotojas ką nors daro puslapyje.
     *
     * Kaip veikia:
     *   1. Sustabdo ankstesnius laikmačius (jei veikė)
     *   2. Paslepia perspėjimą (jei buvo rodomas)
     *   3. Paleidžia naują perspėjimo laikmatį: po 28 min → rodyti perspėjimą
     *   4. Paleidžia naują sesijos laikmatį:   po 30 min → nukreipti į login
     */
    function resetuotiLaikmati() {
        if (sesijosTimer) clearTimeout(sesijosTimer);
        if (ispejimoTimer) clearTimeout(ispejimoTimer);
        pasleptiIspejima();

        /* Po 28 minučių neaktyvumo — rodyti geltoną perspėjimą */
        ispejimoTimer = setTimeout(function() {
            rodytiIspejima();
        }, SESIJOS_LAIKAS - ISPEJIMO_LAIKAS);

        /* Po 30 minučių neaktyvumo — nukreipti į prisijungimo puslapį */
        sesijosTimer = setTimeout(function() {
            window.location.href = '/login.php?sesija_pasibaige=1';
        }, SESIJOS_LAIKAS);
    }

    /**
     * Sukuria ir parodo geltoną perspėjimo juostelę puslapio viršuje.
     * Juostelė rodoma likus 2 minutėms iki sesijos pabaigos.
     * Jei juostelė jau rodoma — nieko nedaro (apsauga nuo dubliavimo).
     */
    function rodytiIspejima() {
        if (ispejimoElementas) return;
        ispejimoElementas = document.createElement('div');
        ispejimoElementas.id = 'sesijos-ispejimas';
        /* Stilius pritaikytas tiesiogiai, kad veiktų nepriklausomai nuo CSS failų */
        ispejimoElementas.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#fff3cd;color:#856404;border-bottom:2px solid #ffc107;padding:12px 20px;text-align:center;z-index:99999;font-family:Inter,sans-serif;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.15);';
        ispejimoElementas.innerHTML = 'Jūsų sesija baigsis po 2 minučių dėl neaktyvumo. Pajudinkite pelę arba paspauskite klavišą, kad pratęstumėte.';
        document.body.appendChild(ispejimoElementas);
    }

    /**
     * Paslepia ir pašalina perspėjimo juostelę iš DOM.
     * Kviečiama, kai vartotojas vėl pradeda veikti.
     */
    function pasleptiIspejima() {
        if (ispejimoElementas) {
            ispejimoElementas.remove();
            ispejimoElementas = null;
        }
    }

    /* Įvykių sąrašas, kurie laikomi "vartotojo aktyvumu".
       Pvz.: pelės judėjimas, klavišo paspaudimas, slinkimas, lietimas (telefonui). */
    var aktyvumoIvykiai = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'];

    /* Paskutinio serverio atnaujinimo laikas — neleidžia siųsti užklausų per dažnai.
       Inicializuojama su Date.now(), kad pirmasis kliktelėjimas iš karto nesukeltų
       užklausos (pirma užklausa bus tik po 30 sekundžių neaktyvumo). */
    var paskutinisAtnaujinimas = Date.now();

    /**
     * Kviečiamas kaskart, kai vartotojas ką nors daro.
     *
     * Veikimo logika:
     *   - Jei nuo paskutinės užklausos praėjo < 30 sekundžių → nieko nedaro
     *     (taupom serverio resursus ir tinklo srautą)
     *   - Priešingu atveju: iš naujo paleidžia laikmatį ir siunčia keepalive
     *     užklausą į sesijos_atnaujinimas.php, kad serveris irgi žinotų,
     *     jog vartotojas aktyvus.
     *   - Jei serveris grąžina 401 (sesija pasibaigė ir serverio pusėje) →
     *     nukreipiama į prisijungimo puslapį.
     */
    function aktyvumoHandler() {
        var dabar = Date.now();
        if (dabar - paskutinisAtnaujinimas < 30000) return;
        paskutinisAtnaujinimas = dabar;
        resetuotiLaikmati();
        fetch('/sesijos_atnaujinimas.php', { method: 'GET', credentials: 'include' })
            .then(function(r) { if (r.status === 401) window.location.href = '/login.php?sesija_pasibaige=1'; })
            .catch(function() { /* Tinklo klaida — nieko nedarome, laikmaitis toliau eina */ });
    }

    /* Nustatome ar šis puslapis yra "viešas" (prisijungimas, slaptažodis).
       Sesijos laikmaitis neaktyvinamas viešuose puslapiuose — jie neturi sesijos. */
    var yraLoginPuslapis = window.location.pathname === '/login.php'
        || window.location.pathname === '/slaptazodis_atstatymas.php'
        || window.location.pathname === '/slaptazodis_keitimas.php';

    /* Registruojame aktyvumo klausytojus ir paleidžiame laikmatį.
       { passive: true } reiškia, kad klausytojas neblokuos slinkimo —
       tai svarbu našumui, ypač mobiliuose įrenginiuose. */
    if (!yraLoginPuslapis) {
        aktyvumoIvykiai.forEach(function(ivykis) {
            document.addEventListener(ivykis, aktyvumoHandler, { passive: true });
        });
        resetuotiLaikmati();
    }
})();
/* Sesijos laikmaitis baigėsi — toliau visi kiti kodai yra globalūs */

/* =========================================================================
   2. ŠONINĖS JUOSTOS VALDYMAS (MOBILUSIS MENIU)
   Mobiliuose įrenginiuose (<768px) šoninė juosta slepiama.
   Atidaroma paspaudus "hamburger" mygtuką (#menuToggle).
   ========================================================================= */
/* =========================================================================
   CSRF APSAUGA — automatinis žetono įterpimas
   Ieško meta žymės "csrf-token" (įterpiama per header.php) ir prideda
   paslėptą lauką "_csrf" į visas POST formas puslapyje.
   ========================================================================= */
(function() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;
    var token = meta.getAttribute('content');
    if (!token) return;
    document.querySelectorAll('form').forEach(function(form) {
        if (form.method.toLowerCase() === 'post' && !form.querySelector('input[name="_csrf"]')) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = '_csrf';
            inp.value = token;
            form.appendChild(inp);
        }
    });
    window.__csrfToken = token;
})();

/* =========================================================================
   CSRF APSAUGA — automatinis žetono pridėjimas prie AJAX užklausų
   Įterpia „X-CSRF-Token" antraštę į visas same-origin POST/PUT/PATCH/DELETE
   fetch() ir XMLHttpRequest užklausas, kad serverio csrfVerify() jas priimtų.
   ========================================================================= */
(function() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : (window.__csrfToken || '');
    if (!token) return;
    var unsafe = /^(POST|PUT|PATCH|DELETE)$/i;
    function sameOrigin(url) {
        if (!url) return true;
        if (url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0) return true;
        return url.indexOf(window.location.origin) === 0;
    }

    if (window.fetch) {
        var origFetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            var method = init.method || (typeof input === 'object' && input ? input.method : 'GET') || 'GET';
            var url = (typeof input === 'string') ? input : (input && input.url) || '';
            if (unsafe.test(method) && sameOrigin(url)) {
                var headers = new Headers(init.headers || (typeof input === 'object' && input ? input.headers : null) || {});
                if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
            return origFetch.call(this, input, init);
        };
    }

    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function(method) {
        this.__csrfUnsafe = unsafe.test(method || '');
        return origOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function() {
        if (this.__csrfUnsafe) {
            try { this.setRequestHeader('X-CSRF-Token', token); } catch (e) {}
        }
        return origSend.apply(this, arguments);
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var sidebarClose = document.getElementById('sidebarClose');

    /* Paspaudus "hamburger" mygtuką — pridedama/pašalinama CSS klasė 'open',
       kuri CSS faile paslepia arba parodo šoninę juostą.
       e.stopPropagation() neleidžia įvykiui "išplaukti" į document lymenį,
       kur būtų tuoj pat uždaryta ką tik atidaryta juosta. */
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });
    }

    /* Šoninėje juostoje yra specialus uždarymo mygtukas (×) — jis pašalina 'open' klasę */
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.remove('open');
        });
    }

    /* Paspaudus bet kur už šoninės juostos ribų — ji užsidaro.
       Tikrinama: ar paspaustas elementas nėra pati juosta arba hamburger mygtukas. */
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });
});

/* =========================================================================
   3. MODALINIŲ LANGŲ VALDYMAS
   Modalinis langas — iššokantis dialogo langas (pvz. "Sukurti užsakymą",
   "Redaguoti klientą"). Veikia su CSS klase 'active' ant .modal-overlay elemento.

   Prieinamumas (accessibility):
   - Fokusas automatiškai pereina į pirmą lango laukelį
   - Po uždarymo fokusas grįžta į mygtuką, kuris atidarė langą
   - Tab klavišas "sukasi" tik lango viduje (fokuso spąstai)
   - Escape klavišas uždaro atidarytą langą
   ========================================================================= */

/**
 * Saugo nuorodą į elementą, kuris buvo aktyvus PRIEŠ atidarant modalą.
 * Reikalinga, kad po uždarymo fokusas grįžtų atgal (pvz. į "Redaguoti" mygtuką).
 */
var _modalTriggerElement = null;

/**
 * Atidaro modalinį langą pagal jo HTML elemento ID.
 *
 * @param {string} id - Modalinio lango elemento ID (pvz. 'modalKurti')
 *
 * Kaip veikia:
 *   1. Išsaugo dabartinį aktyvų elementą (kad po uždarymo grąžintume fokusą)
 *   2. Prideda CSS klasę 'active' → modalas tampa matomas
 *   3. Nustato ARIA atributus ekrano skaitytuvams
 *   4. Po 50ms perkelia fokusą į pirmą lango laukelį / mygtuką
 */
function openModal(id) {
    _modalTriggerElement = document.activeElement;
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        /* Ieškome pirmo interaktyvaus elemento lange (bet ne slapto input ar uždarymo mygtuko) */
        var firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea, button:not(.modal-close)');
        if (firstInput) setTimeout(function() { firstInput.focus(); }, 50);
    }
}

/**
 * Uždaro modalinį langą pagal jo HTML elemento ID.
 *
 * @param {string} id - Modalinio lango elemento ID
 *
 * Kaip veikia:
 *   1. Pašalina CSS klasę 'active' → modalas paslepiamas
 *   2. Grąžina fokusą į elementą, kuris atidarė šį modalą
 */
function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        /* Grąžiname fokusą į trigerio elementą (pvz. "Redaguoti" mygtuką) */
        if (_modalTriggerElement) {
            _modalTriggerElement.focus();
            _modalTriggerElement = null;
        }
    }
}

/**
 * Escape klavišas — uždaro bet kurį atidarytą modalinį langą.
 *
 * Tikrinama pagal tris atvejus:
 *   - .modal-overlay.active        — standartinis būdas (openModal funkcija)
 *   - [style*="display: flex"]     — kai modalas atidarytas tiesiai per style
 *   - [style*="display:flex"]      — tas pats be tarpo (skirtingi rašymo būdai)
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var activeModals = document.querySelectorAll('.modal-overlay.active, .modal-overlay[style*="display: flex"], .modal-overlay[style*="display:flex"]');
        activeModals.forEach(function(m) {
            m.classList.remove('active');
            m.style.display = 'none';
        });
        /* Grąžiname fokusą į trigerio elementą */
        if (_modalTriggerElement) {
            _modalTriggerElement.focus();
            _modalTriggerElement = null;
        }
    }
});

/**
 * Tab klavišas — fokuso spąstai modaliniame lange.
 *
 * Kai modalas atidarytas, Tab klavišas neturi leisti fokusui išeiti
 * už lango ribų (pvz. pereiti į foninį puslapį). Todėl:
 *   - Tab (į priekį): jei fokusas ant PASKUTINIO elemento → šokinėja į PIRMĄ
 *   - Shift+Tab (atgal): jei fokusas ant PIRMO elemento → šokinėja į PASKUTINĮ
 *
 * Taip vartotojas gali Tab klavišu "sukiotis" tik lango viduje.
 */
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    var activeModal = document.querySelector('.modal-overlay.active, .modal-overlay[style*="display: flex"], .modal-overlay[style*="display:flex"]');
    if (!activeModal) return;

    /* Surandame visus elementus, kuriems galima perduoti fokusą lange */
    var focusable = activeModal.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (focusable.length === 0) return;

    var first = focusable[0];
    var last = focusable[focusable.length - 1];

    if (e.shiftKey) {
        /* Shift+Tab ir fokusas ant pirmo elemento → šokinėjam į paskutinį */
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
    } else {
        /* Tab ir fokusas ant paskutinio elemento → šokinėjam į pirmą */
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
});

/* =========================================================================
   4. IŠTRYNIMO PATVIRTINIMAS
   Naudojama visur, kur yra "Ištrinti" mygtukas su patvirtinimu.
   Privalumas: naršyklės confirm() dialogo nereikia jokio papildomo HTML.
   ========================================================================= */

/**
 * Rodo patvirtinimo dialogą ir ištrina elementą, jei vartotojas sutinka.
 *
 * @param {string} url  - Puslapis, į kurį siunčiama forma (pvz. 'uzsakovai.php')
 * @param {string} name - Elemento pavadinimas, rodomas dialoge (pvz. 'UAB Statybų centras')
 *
 * Kaip veikia:
 *   Paprastas confirm() dialogas yra sinchroninis — jis blokuoja puslapį ir
 *   laukia vartotojo atsakymo. Jei vartotojas paspaudė "Gerai":
 *   1. Dinamiškai sukuriama HTML forma su POST metodu
 *   2. Pridedamas paslėptas laukelis action=delete (PHP pusė tikrina šį laukelį)
 *   3. Forma pridedama į puslapį ir iš karto pateikiama
 *   Naudojama forma, o ne fetch(), kad PHP galėtų atlikti pilną puslapio
 *   perkrovimą ir parodyti sėkmės/klaidos pranešimą.
 */
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
        if (window.__csrfToken) {
            var csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_csrf';
            csrf.value = window.__csrfToken;
            form.appendChild(csrf);
        }
        document.body.appendChild(form);
        form.submit();
    }
}
