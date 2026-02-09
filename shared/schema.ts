import { pgTable, text, serial, integer, boolean, timestamp, varchar, jsonb, numeric, date, smallint, bytea } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod";

export const vartotojai = pgTable("vartotojai", {
  id: serial("id").primaryKey(),
  vardas: varchar("vardas", { length: 100 }).notNull(),
  pavarde: varchar("pavarde", { length: 100 }),
  el_pastas: varchar("el_pastas", { length: 255 }),
  slaptazodis: varchar("slaptazodis", { length: 255 }).notNull(),
  sukurta: text("sukurta").default("CURRENT_TIMESTAMP").notNull(),
  role: text("role").default("user").notNull(),
  login_token: varchar("login_token", { length: 255 }),
  token_galiojimas: timestamp("token_galiojimas"),
  patvirtintas: boolean("patvirtintas").default(false),
  patvirtino_id: integer("patvirtino_id"),
  patvirtinimo_data: timestamp("patvirtinimo_data"),
});

export const uzsakovai = pgTable("uzsakovai", {
  id: serial("id").primaryKey(),
  uzsakovas: varchar("uzsakovas", { length: 100 }),
});

export const objektai = pgTable("objektai", {
  id: serial("id").primaryKey(),
  pavadinimas: varchar("pavadinimas", { length: 100 }).notNull(),
});

export const gaminiu_rusys = pgTable("gaminiu_rusys", {
  id: serial("id").primaryKey(),
  pavadinimas: varchar("pavadinimas", { length: 100 }).notNull(),
});

export const uzsakymai = pgTable("uzsakymai", {
  id: serial("id").primaryKey(),
  uzsakymo_numeris: varchar("uzsakymo_numeris", { length: 50 }),
  sukurtas: text("sukurtas").default("CURRENT_TIMESTAMP").notNull(),
  kiekis: integer("kiekis"),
  uzsakovas_id: integer("uzsakovas_id"),
  vartotojas_id: integer("vartotojas_id"),
  objektas_id: integer("objektas_id"),
  gaminiu_rusis_id: integer("gaminiu_rusis_id").default(1).notNull(),
});

export const gaminio_tipai = pgTable("gaminio_tipai", {
  id: serial("id").primaryKey(),
  gaminio_tipas: varchar("gaminio_tipas", { length: 100 }),
  grupe: varchar("grupe", { length: 100 }).notNull(),
  atitikmuo_kodas: varchar("atitikmuo_kodas", { length: 10 }),
});

export const gaminiai = pgTable("gaminiai", {
  id: serial("id").primaryKey(),
  uzsakymo_id: integer("uzsakymo_id"),
  gaminio_numeris: varchar("gaminio_numeris", { length: 50 }),
  gaminio_tipas_id: integer("gaminio_tipas_id"),
  protokolo_nr: varchar("protokolo_nr", { length: 100 }),
  atitikmuo_kodas: varchar("atitikmuo_kodas", { length: 20 }),
});

export const mt_komponentai = pgTable("mt_komponentai", {
  id: serial("id").primaryKey(),
  eiles_numeris: integer("eiles_numeris").notNull(),
  gamintojo_kodas: varchar("gamintojo_kodas", { length: 100 }),
  kiekis: integer("kiekis").notNull(),
  aprasymas: text("aprasymas"),
  gamintojas: varchar("gamintojas", { length: 100 }),
  gaminio_id: integer("gaminio_id"),
  parinkta_projektui: integer("parinkta_projektui").default(0),
});

export const prietaisai = pgTable("prietaisai", {
  id: serial("id").primaryKey(),
  pavadinimas: varchar("pavadinimas", { length: 200 }),
  gamintojas: varchar("gamintojas", { length: 200 }),
  tipas: varchar("tipas", { length: 200 }),
  serijinis_nr: varchar("serijinis_nr", { length: 200 }),
  kalibravimo_data: text("kalibravimo_data"),
  kalibravimo_galiojimas: text("kalibravimo_galiojimas"),
  sertifikato_nr: varchar("sertifikato_nr", { length: 200 }),
  matavimo_diapazonas: text("matavimo_diapazonas"),
  tikslumas: text("tikslumas"),
  vartotojas_id: integer("vartotojas_id"),
  kalibravimo_istaiga: varchar("kalibravimo_istaiga", { length: 255 }),
  kategorija: varchar("kategorija", { length: 100 }),
});

export const insertVartotojasSchema = createInsertSchema(vartotojai).omit({ id: true, sukurta: true });
export const insertUzsakymasSchema = createInsertSchema(uzsakymai).omit({ id: true, sukurtas: true });
export const insertGaminysSchema = createInsertSchema(gaminiai).omit({ id: true });
export const insertUzsakovasSchema = createInsertSchema(uzsakovai).omit({ id: true });
export const insertObjektasSchema = createInsertSchema(objektai).omit({ id: true });
export const insertGaminioTipasSchema = createInsertSchema(gaminio_tipai).omit({ id: true });
export const insertKomponentasSchema = createInsertSchema(mt_komponentai).omit({ id: true });

export type Vartotojas = typeof vartotojai.$inferSelect;
export type InsertVartotojas = z.infer<typeof insertVartotojasSchema>;

export type Uzsakymas = typeof uzsakymai.$inferSelect;
export type InsertUzsakymas = z.infer<typeof insertUzsakymasSchema>;

export type Gaminys = typeof gaminiai.$inferSelect;
export type InsertGaminys = z.infer<typeof insertGaminysSchema>;

export type Uzsakovas = typeof uzsakovai.$inferSelect;
export type InsertUzsakovas = z.infer<typeof insertUzsakovasSchema>;
export type Objektas = typeof objektai.$inferSelect;
export type InsertObjektas = z.infer<typeof insertObjektasSchema>;
export type GaminioTipas = typeof gaminio_tipai.$inferSelect;
export type InsertGaminioTipas = z.infer<typeof insertGaminioTipasSchema>;
export type Komponentas = typeof mt_komponentai.$inferSelect;
export type InsertKomponentas = z.infer<typeof insertKomponentasSchema>;
export type Prietaisas = typeof prietaisai.$inferSelect;
