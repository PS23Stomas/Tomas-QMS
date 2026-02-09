import type { Express } from "express";
import { createServer, type Server } from "http";
import { storage } from "./storage";
import { api, errorSchemas } from "@shared/routes";
import { z } from "zod";
import session from "express-session";
import passport from "passport";
import { Strategy as LocalStrategy } from "passport-local";
import { scrypt, randomBytes, timingSafeEqual } from "crypto";
import { promisify } from "util";

const scryptAsync = promisify(scrypt);

async function hashPassword(password: string) {
  const salt = randomBytes(16).toString("hex");
  const buf = (await scryptAsync(password, salt, 64)) as Buffer;
  return `${buf.toString("hex")}.${salt}`;
}

async function comparePasswords(supplied: string, stored: string) {
  const [hashed, salt] = stored.split(".");
  const hashedBuf = Buffer.from(hashed, "hex");
  const suppliedBuf = (await scryptAsync(supplied, salt, 64)) as Buffer;
  return timingSafeEqual(hashedBuf, suppliedBuf);
}

import express from "express";
import path from "path";

export async function registerRoutes(
  httpServer: Server,
  app: Express
): Promise<Server> {
  // Setup Auth
  passport.use(
    new LocalStrategy(
      { usernameField: "el_pastas", passwordField: "slaptazodis" },
      async (email, password, done) => {
        try {
          const user = await storage.getUserByEmail(email);
          if (!user) return done(null, false, { message: "Vartotojas nerastas" });
          
          // In a real migration, we'd check hash format. 
          // For seed data we use our hash. For legacy data, we might need to support their hash.
          // Assuming we reset passwords or use new ones.
          const isValid = await comparePasswords(password, user.slaptazodis);
          if (!isValid) return done(null, false, { message: "Neteisingas slaptažodis" });
          
          return done(null, user);
        } catch (err) {
          return done(err);
        }
      }
    )
  );

  passport.serializeUser((user: any, done) => done(null, user.id));
  passport.deserializeUser(async (id: number, done) => {
    try {
      const user = await storage.getUser(id);
      done(null, user);
    } catch (err) {
      done(err);
    }
  });

  app.use(session({
    secret: process.env.SESSION_SECRET || "secret",
    resave: false,
    saveUninitialized: false,
    cookie: { secure: process.env.NODE_ENV === "production" }
  }));
  app.use(passport.initialize());
  app.use(passport.session());

  // Protect uploads
  app.use("/uploads", (req, res, next) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    next();
  }, express.static(path.join(process.cwd(), "uploads")));

  // API Routes

  // Auth
  app.post(api.auth.login.path, passport.authenticate("local"), (req, res) => {
    res.json(req.user);
  });

  app.post(api.auth.logout.path, (req, res) => {
    req.logout(() => {
      res.sendStatus(200);
    });
  });

  app.get(api.auth.me.path, (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    res.json(req.user);
  });

  // Orders (Uzsakymai)
  app.get(api.uzsakymai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const orders = await storage.getOrders();
    res.json(orders);
  });

  app.post(api.uzsakymai.create.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    try {
      const input = api.uzsakymai.create.input.parse(req.body);
      const order = await storage.createOrder({
        ...input,
        vartotojas_id: (req.user as any).id,
        gaminiu_rusis_id: 1 // Default
      });
      res.status(201).json(order);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({ message: err.errors[0].message });
      }
      throw err;
    }
  });

  app.get(api.uzsakymai.get.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const order = await storage.getOrder(Number(req.params.id));
    if (!order) return res.status(404).json({ message: "Užsakymas nerastas" });
    res.json(order);
  });

  app.put(api.uzsakymai.update.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    try {
      const input = api.uzsakymai.update.input.parse(req.body);
      const order = await storage.updateOrder(Number(req.params.id), input);
      if (!order) return res.status(404).json({ message: "Užsakymas nerastas" });
      res.json(order);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({ message: err.errors[0].message });
      }
      throw err;
    }
  });

  app.delete(api.uzsakymai.delete.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    await storage.deleteOrder(Number(req.params.id));
    res.sendStatus(204);
  });

  // Products (Gaminiai)
  app.get(api.gaminiai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const orderId = req.query.uzsakymo_id ? Number(req.query.uzsakymo_id) : undefined;
    const products = await storage.getProducts(orderId);
    res.json(products);
  });

  app.post(api.gaminiai.create.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    try {
      const input = api.gaminiai.create.input.parse(req.body);
      const product = await storage.createProduct(input);
      res.status(201).json(product);
    } catch (err) {
      if (err instanceof z.ZodError) {
        return res.status(400).json({ message: err.errors[0].message });
      }
      throw err;
    }
  });

  // Clients
  app.get(api.uzsakovai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const clients = await storage.getClients();
    res.json(clients);
  });

  // Objects
  app.get(api.objektai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const objects = await storage.getObjects();
    res.json(objects);
  });

  // Product Types
  app.get(api.gaminio_tipai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const types = await storage.getProductTypes();
    res.json(types);
  });

  await seedDatabase();

  return httpServer;
}

async function seedDatabase() {
  const existingUsers = await storage.getUserByEmail("admin@mt.lt");
  if (!existingUsers) {
    const password = await hashPassword("admin123");
    await storage.createUser({
      vardas: "Admin",
      pavarde: "Vartotojas",
      el_pastas: "admin@mt.lt",
      slaptazodis: password,
      role: "admin",
      patvirtintas: true
    });
    console.log("Seeded admin user");
  }
}
