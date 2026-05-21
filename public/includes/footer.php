<?php
/**
 * ==========================================================================
 *  INCLUDES/FOOTER.PHP — Bendras puslapio poraštės šablonas
 * ==========================================================================
 *
 *  Paskirtis:  Uždaryti visus HTML elementus, kuriuos atidarė header.php,
 *              įkelti kliento pusės JavaScript ir baigti HTML dokumentą.
 *
 *  Šis failas įtraukiamas kiekvieno puslapio PABAIGOJE (prieš pat PHP failo
 *  pabaigą). Jis yra pora su header.php — kartu jie "apgaubia" kiekvieno
 *  puslapio turinį.
 *
 *  Ką šis failas daro:
 *    1. Uždaro <main class="content-area"> — atidarytas header.php pabaigoje
 *    2. Uždaro <div class="main-content"> — pagrindinis turinio blokas
 *    3. Uždaro <div class="app-layout">  — visas puslapio išdėstymas
 *    4. Įkelia /js/app.js — kliento pusės JavaScript (šoninės juostos
 *       atidarimas, modalai, klaviatūros valdymas ir kt.)
 *    5. Uždaro <body> ir <html> — HTML dokumento pabaiga
 *
 *  Pastaba: JavaScript įkeliamas puslapio PABAIGOJE (ne <head>), kad
 *  naršyklė pirmiau parodytų HTML turinį ir tik tada vykdytų skriptus.
 *  Tai pagreitina puslapio įkėlimą.
 * ==========================================================================
 */
?>
            </main>
        </div><!-- /.main-content -->
    </div><!-- /.app-layout -->
    <!-- JavaScript įkeliamas puslapio pabaigoje — greičiau matomas turinys -->
    <script src="/js/app.js"></script>
</body>
</html>
