import { 
  vartotojai, uzsakymai, gaminiai, uzsakovai, objektai, gaminio_tipai, mt_komponentai,
  type Vartotojas, type InsertVartotojas,
  type Uzsakymas, type InsertUzsakymas,
  type Gaminys, type InsertGaminys,
  type Uzsakovas, type InsertUzsakovas,
  type Objektas, type InsertObjektas,
  type GaminioTipas, type InsertGaminioTipas,
  type Komponentas, type InsertKomponentas
} from "@shared/schema";
import { db } from "./db";
import { eq, desc } from "drizzle-orm";

export interface IStorage {
  // Users
  getUser(id: number): Promise<Vartotojas | undefined>;
  getUserByEmail(email: string): Promise<Vartotojas | undefined>;
  createUser(user: InsertVartotojas): Promise<Vartotojas>;
  
  // Orders
  getOrders(): Promise<Uzsakymas[]>;
  getOrder(id: number): Promise<Uzsakymas | undefined>;
  createOrder(order: InsertUzsakymas): Promise<Uzsakymas>;
  updateOrder(id: number, order: Partial<InsertUzsakymas>): Promise<Uzsakymas>;
  deleteOrder(id: number): Promise<void>;

  // Products
  getProducts(orderId?: number): Promise<Gaminys[]>;
  getProduct(id: number): Promise<Gaminys | undefined>;
  createProduct(product: InsertGaminys): Promise<Gaminys>;
  
  // Clients
  getClients(): Promise<Uzsakovas[]>;
  
  // Objects
  getObjects(): Promise<Objektas[]>;
  
  // Product Types
  getProductTypes(): Promise<GaminioTipas[]>;
}

export class DatabaseStorage implements IStorage {
  async getUser(id: number): Promise<Vartotojas | undefined> {
    const [user] = await db.select().from(vartotojai).where(eq(vartotojai.id, id));
    return user;
  }

  async getUserByEmail(email: string): Promise<Vartotojas | undefined> {
    const [user] = await db.select().from(vartotojai).where(eq(vartotojai.el_pastas, email));
    return user;
  }

  async createUser(insertUser: InsertVartotojas): Promise<Vartotojas> {
    const [user] = await db.insert(vartotojai).values(insertUser).returning();
    return user;
  }

  async getOrders(): Promise<Uzsakymas[]> {
    return await db.select().from(uzsakymai).orderBy(desc(uzsakymai.id));
  }

  async getOrder(id: number): Promise<Uzsakymas | undefined> {
    const [order] = await db.select().from(uzsakymai).where(eq(uzsakymai.id, id));
    return order;
  }

  async createOrder(insertOrder: InsertUzsakymas): Promise<Uzsakymas> {
    const [order] = await db.insert(uzsakymai).values(insertOrder).returning();
    return order;
  }

  async updateOrder(id: number, updateOrder: Partial<InsertUzsakymas>): Promise<Uzsakymas> {
    const [order] = await db.update(uzsakymai).set(updateOrder).where(eq(uzsakymai.id, id)).returning();
    return order;
  }

  async deleteOrder(id: number): Promise<void> {
    await db.delete(uzsakymai).where(eq(uzsakymai.id, id));
  }

  async getProducts(orderId?: number): Promise<Gaminys[]> {
    if (orderId) {
      return await db.select().from(gaminiai).where(eq(gaminiai.uzsakymo_id, orderId));
    }
    return await db.select().from(gaminiai);
  }

  async getProduct(id: number): Promise<Gaminys | undefined> {
    const [product] = await db.select().from(gaminiai).where(eq(gaminiai.id, id));
    return product;
  }

  async createProduct(insertProduct: InsertGaminys): Promise<Gaminys> {
    const [product] = await db.insert(gaminiai).values(insertProduct).returning();
    return product;
  }

  async getClients(): Promise<Uzsakovas[]> {
    return await db.select().from(uzsakovai);
  }

  async getObjects(): Promise<Objektas[]> {
    return await db.select().from(objektai);
  }

  async getProductTypes(): Promise<GaminioTipas[]> {
    return await db.select().from(gaminio_tipai);
  }
}

export const storage = new DatabaseStorage();
