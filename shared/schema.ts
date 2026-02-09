import { pgTable, text, serial, integer, boolean, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod";

// === VARTOTOJAI (Users) ===
export const vartotojai = pgTable("vartotojai", {
  id: serial("id").primaryKey(),
  vardas: text("vardas").notNull(),
  pavarde: text("pavarde"),
  el_pastas: text("el_pastas"),
  slaptazodis: text("slaptazodis").notNull(),
  sukurta: text("sukurta").default("CURRENT_TIMESTAMP").notNull(),
  role: text("role").default("user").notNull(),
  patvirtintas: boolean("patvirtintas").default(false),
});

// === UZSAKOVAI (Clients) ===
export const uzsakovai = pgTable("uzsakovai", {
  id: serial("id").primaryKey(),
  uzsakovas: text("uzsakovas"), // Name
});

// === OBJEKTAI (Objects) ===
export const objektai = pgTable("objektai", {
  id: serial("id").primaryKey(),
  pavadinimas: text("pavadinimas").notNull(),
});

// === GAMINIU RUSYS (Product Classes) ===
export const gaminiu_rusys = pgTable("gaminiu_rusys", {
  id: serial("id").primaryKey(),
  pavadinimas: text("pavadinimas").notNull(),
});

// === UZSAKYMAI (Orders) ===
export const uzsakymai = pgTable("uzsakymai", {
  id: serial("id").primaryKey(),
  uzsakymo_numeris: text("uzsakymo_numeris"),
  sukurtas: text("sukurtas").default("CURRENT_TIMESTAMP").notNull(),
  kiekis: integer("kiekis"),
  uzsakovas_id: integer("uzsakovas_id").references(() => uzsakovai.id),
  vartotojas_id: integer("vartotojas_id").references(() => vartotojai.id),
  objektas_id: integer("objektas_id").references(() => objektai.id),
  gaminiu_rusis_id: integer("gaminiu_rusis_id").default(1).notNull().references(() => gaminiu_rusys.id),
});

// === GAMINIO TIPAI (Product Types) ===
export const gaminio_tipai = pgTable("gaminio_tipai", {
  id: serial("id").primaryKey(),
  gaminio_tipas: text("gaminio_tipas"),
  grupe: text("grupe").notNull(),
  atitikmuo_kodas: text("atitikmuo_kodas"),
});

// === GAMINIAI (Products) ===
export const gaminiai = pgTable("gaminiai", {
  id: serial("id").primaryKey(),
  uzsakymo_id: integer("uzsakymo_id").references(() => uzsakymai.id),
  gaminio_numeris: text("gaminio_numeris"),
  gaminio_tipas_id: integer("gaminio_tipas_id").references(() => gaminio_tipai.id),
  protokolo_nr: text("protokolo_nr"),
  atitikmuo_kodas: text("atitikmuo_kodas"),
});

// === MT KOMPONENTAI (Components) ===
export const mt_komponentai = pgTable("mt_komponentai", {
  id: serial("id").primaryKey(), // Changed to serial for simplicity, usually manual integer
  eiles_numeris: integer("eiles_numeris").notNull(),
  gamintojo_kodas: text("gamintojo_kodas"),
  kiekis: integer("kiekis").notNull(),
  aprasymas: text("aprasymas"),
  gamintojas: text("gamintojas"),
  gaminio_id: integer("gaminio_id").references(() => gaminiai.id),
  parinkta_projektui: integer("parinkta_projektui").default(0), // smallint in SQL
});

// === SCHEMAS ===
export const insertVartotojasSchema = createInsertSchema(vartotojai).omit({ id: true, sukurta: true });
export const insertUzsakymasSchema = createInsertSchema(uzsakymai).omit({ id: true, sukurtas: true });
export const insertGaminysSchema = createInsertSchema(gaminiai).omit({ id: true });
export const insertUzsakovasSchema = createInsertSchema(uzsakovai).omit({ id: true });
export const insertObjektasSchema = createInsertSchema(objektai).omit({ id: true });
export const insertGaminioTipasSchema = createInsertSchema(gaminio_tipai).omit({ id: true });
export const insertKomponentasSchema = createInsertSchema(mt_komponentai).omit({ id: true });

// === TYPES ===
export type Vartotojas = typeof vartotojai.$inferSelect;
export type InsertVartotojas = z.infer<typeof insertVartotojasSchema>;

export type Uzsakymas = typeof uzsakymai.$inferSelect;
export type InsertUzsakymas = z.infer<typeof insertUzsakymasSchema>;

export type Gaminys = typeof gaminiai.$inferSelect;
export type InsertGaminys = z.infer<typeof insertGaminysSchema>;

export type Uzsakovas = typeof uzsakovai.$inferSelect;
export type Objektas = typeof objektai.$inferSelect;
export type GaminioTipas = typeof gaminio_tipai.$inferSelect;
export type Komponentas = typeof mt_komponentai.$inferSelect;
