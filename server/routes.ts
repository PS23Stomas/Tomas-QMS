import type { Express } from "express";
import type { Server } from "http";
import { storage } from "./storage";
import { api } from "@shared/routes";
import { z } from "zod";
import session from "express-session";
import passport from "passport";
import { Strategy as LocalStrategy } from "passport-local";
import bcrypt from "bcryptjs";
import express from "express";
import path from "path";

export async function registerRoutes(
  httpServer: Server,
  app: Express
): Promise<Server> {
  passport.use(
    new LocalStrategy(
      { usernameField: "el_pastas", passwordField: "slaptazodis" },
      async (email, password, done) => {
        try {
          const user = await storage.getUserByEmail(email);
          if (!user) return done(null, false, { message: "Vartotojas nerastas" });

          const storedHash = user.slaptazodis.replace(/^\$2y\$/, "$2a$");
          const isValid = await bcrypt.compare(password, storedHash);
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

  app.use("/uploads", (req, res, next) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    next();
  }, express.static(path.join(process.cwd(), "uploads")));

  app.post(api.auth.login.path, (req, res, next) => {
    passport.authenticate("local", (err: any, user: any, info: any) => {
      if (err) return next(err);
      if (!user) return res.status(401).json({ message: info?.message || "Prisijungimas nepavyko" });
      req.logIn(user, (err) => {
        if (err) return next(err);
        const { slaptazodis, login_token, token_galiojimas, ...safeUser } = user;
        res.json(safeUser);
      });
    })(req, res, next);
  });

  app.post(api.auth.logout.path, (req, res) => {
    req.logout(() => {
      res.sendStatus(200);
    });
  });

  app.get(api.auth.me.path, (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const user = req.user as any;
    const { slaptazodis, login_token, token_galiojimas, ...safeUser } = user;
    res.json(safeUser);
  });

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
        gaminiu_rusis_id: input.gaminiu_rusis_id || 1,
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

  app.get(api.uzsakovai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const clients = await storage.getClients();
    res.json(clients);
  });

  app.get(api.objektai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const objects = await storage.getObjects();
    res.json(objects);
  });

  app.get(api.gaminio_tipai.list.path, async (req, res) => {
    if (!req.isAuthenticated()) return res.sendStatus(401);
    const types = await storage.getProductTypes();
    res.json(types);
  });

  return httpServer;
}
