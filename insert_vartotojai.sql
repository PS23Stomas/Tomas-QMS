-- ==========================================================================
--  Vartotojų įkėlimas į public.vartotojai (16 įrašų)
--  Paleisk Supabase SQL Editor lange.
--  parasas (bytea) visiems tuščias -> praleidžiamas (liks NULL).
-- ==========================================================================

-- 1) Trūkstamas stulpelis (jo nebuvo schemos eksporte, bet yra gyvoje DB ir
--    jo reikalauja login.php). Saugu paleisti kartotinai.
ALTER TABLE public.vartotojai
    ADD COLUMN IF NOT EXISTS aktyvus boolean NOT NULL DEFAULT true;

-- 2) Duomenys
INSERT INTO public.vartotojai
    (id, vardas, pavarde, el_pastas, slaptazodis, sukurta, role,
     login_token, token_galiojimas, patvirtintas, patvirtino_id,
     patvirtinimo_data, parasas_tipas, pareigos, aktyvus)
VALUES
 (8,  'Tomas',    'Atkociunas', 'tomas.atkociunas@elga.lt',   '$2y$10$Dqhkp2fQuFjeUD6qbRH7x.Cz6NBemKn6S1H/EaBPCu2TZpP8jeR7u', '2026-06-15 10:16:53.617191+00', 'vartotojas',     NULL, NULL,                          false, NULL, NULL,                      NULL, 'Kokybės inžinierius', true),
 (9,  'Tomas',    'Viržintas',  'virzintas@gmail.com',        '$2y$10$uhX1Z6AQwDKYLrsw/cnsEuDeFpPLaA7EBhTOPiLj6vSUmjpNIGBjm', '2025-06-06 18:54:31',           'administratorius', '7ece3f71cdcb00b691e0d71e0404f273b9950bd60d284f17437e70c4e8aacf12', '2026-05-22T21:33:06.000Z', true,  NULL, NULL,                      NULL, '', true),
 (13, 'Nerius',   'Grublys',    'nerius.grublys@elga.lt',     '$2y$10$zPqp5tht/Uu8vvWGQ3Dklu6BUIgoTZLuPsEcr1BkPX8on77.piiIG', '2025-08-05 11:51:17',           'user',           NULL, NULL,                          true,  NULL, NULL,                      NULL, '', true),
 (14, 'Aivaras',  'Čerkauskas', NULL,                         '$2y$10$s5Si5Yl.bffhy1i.qvyV7u5Qjl8vlIJrKg92tapGFduZPLe2DUbym', '2026-06-15 10:16:54.018222+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (15, 'Tadas',    'Bumblauskas','Tad@darb.com',               '$2y$10$2caP6mH2WKorLSNwb8LjHuHRqXohHraxyQVtdyDlFqVLR7oZklxnG', '2025-08-08 07:11:30',           'user',           NULL, NULL,                          true,  NULL, NULL,                      NULL, '', true),
 (17, 'Demo',     'Vartotojas', 'demo@example.com',           '$2y$10$MlcITFAHEqV3Wr8j1M2cJOYGtkbQ/cd.wOOCCGSEdyPClzsJpRHTe', '2026-06-15 10:16:54.281328+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (19, 'GVX',      'Darbuotojas',NULL,                         '$2y$10$jUt.tjcfh3evryq0E.Y1nOcKixDgNOaLLiEQWXuVB7L14R.3qxc8q', '2026-06-15 10:16:54.41077+00',  'skaitytojas',    NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (21, 'Sigitas',  'Dimša',      'sigitas.dimsa@elga.lt',      '$2y$10$2/WzN9HdvRmmkPC3FbX3AeSJL29KNq42CeBl42TpMvwssRjgoTg1m', '2026-06-15 10:16:54.542047+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (22, 'Augustas', 'Volbekas',   'augustas@elga.lt',           '$2y$10$idskLuCrX2QGy2yQGUFmDe6Brvz1.br76UZLyCzyJZzu/CtcmjcwK', '2026-06-15 10:16:54.670466+00', 'administratorius', NULL, NULL,                        true,  9,    '2026-06-17T05:58:39.877Z', NULL, '', true),
 (23, 'Saulius',  'Vidmantas',  NULL,                         '$2y$10$7qz2Hgbh1NeuL24NvLI6huYTB7wKMedXAORU24usnNbHkziJo/rUu', '2026-06-15 10:16:54.799386+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (24, 'Valdas',   'Meiliunas',  'valdas@elga.lt',             '$2y$10$a6UJqT6TYETourT4XLlEiesRPWpMTmk6xAlJWnq10aZy8S6YpanRO', '2026-06-15 10:16:54.928975+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (25, 'Laurynas', 'Butkys',     'laurynas.butkys@elga.lt',    '$2y$10$OG8jE07qL8pxHlRSUgr69uFqGxlHTXeduKEQYxzrSxsrjw1BItnO6', '2026-06-15 10:16:55.057895+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (26, 'Laimutis', 'Meiliunas',  'Laimutis.Meiliunas@elga.lt', '$2y$10$FjEgOAgKdff6fKxXYqqrX.Sw3whtzRXgMAEtCWaXPmDQBRvl8i9um', '2026-06-15 10:16:55.185351+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (27, 'Kestutis', 'Ruzgis',     'ruzgis@elga.lt',             '$2y$10$qTAUX7xU0N/oTNJSu8fiV.iyZekNpsbJWg0WD5ZyH1QzPLbmtXREi', '2026-06-15 10:16:55.312671+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (28, 'Aivaras',  'Berneckis',  'aivaras@elga.lt',            '$2y$10$a/zn66K9I03g8bc88/jrleViFr.WxxbEwO6Uc..68LNDlmls4h6eS', '2026-06-15 10:16:55.442632+00', 'user',           NULL, NULL,                          false, NULL, NULL,                      NULL, '', true),
 (29, 'Robertas', 'Dapkus',     'dapkus@elga.lt',             '$2y$10$VBiYZbb6D3kzJGZWF8oqwe6ROfIiqxkxNQPxEU/xGUWU93Vtv4y/C', '2026-06-15 10:16:55.569629+00', 'vartotojas',     NULL, NULL,                          true,  9,    '2026-06-20T09:37:35.840Z', NULL, '', true);

-- 3) Sekos sinchronizavimas (kad nauji vartotojai negautų jau užimto id)
SELECT setval('public.vartotojai_id_seq',
              COALESCE((SELECT MAX(id) FROM public.vartotojai), 0) + 1, false);
