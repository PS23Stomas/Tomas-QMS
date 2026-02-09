import { z } from 'zod';
import { 
  insertVartotojasSchema, 
  insertUzsakymasSchema, 
  insertGaminysSchema,
  vartotojai,
  uzsakymai,
  gaminiai,
  uzsakovai,
  objektai,
  gaminio_tipai,
  mt_komponentai
} from './schema';

export const errorSchemas = {
  validation: z.object({
    message: z.string(),
    field: z.string().optional(),
  }),
  notFound: z.object({
    message: z.string(),
  }),
  internal: z.object({
    message: z.string(),
  }),
  unauthorized: z.object({
    message: z.string(),
  }),
};

export const api = {
  auth: {
    login: {
      method: 'POST' as const,
      path: '/api/auth/login' as const,
      input: z.object({
        el_pastas: z.string(),
        slaptazodis: z.string(),
      }),
      responses: {
        200: z.custom<typeof vartotojai.$inferSelect>(),
        401: errorSchemas.unauthorized,
      },
    },
    logout: {
      method: 'POST' as const,
      path: '/api/auth/logout' as const,
      responses: {
        200: z.void(),
      },
    },
    me: {
      method: 'GET' as const,
      path: '/api/auth/me' as const,
      responses: {
        200: z.custom<typeof vartotojai.$inferSelect>(),
        401: errorSchemas.unauthorized,
      },
    }
  },
  uzsakymai: {
    list: {
      method: 'GET' as const,
      path: '/api/uzsakymai' as const,
      responses: {
        200: z.array(z.custom<typeof uzsakymai.$inferSelect>()),
      },
    },
    create: {
      method: 'POST' as const,
      path: '/api/uzsakymai' as const,
      input: insertUzsakymasSchema,
      responses: {
        201: z.custom<typeof uzsakymai.$inferSelect>(),
        400: errorSchemas.validation,
      },
    },
    get: {
      method: 'GET' as const,
      path: '/api/uzsakymai/:id' as const,
      responses: {
        200: z.custom<typeof uzsakymai.$inferSelect>(),
        404: errorSchemas.notFound,
      },
    },
    update: {
      method: 'PUT' as const,
      path: '/api/uzsakymai/:id' as const,
      input: insertUzsakymasSchema.partial(),
      responses: {
        200: z.custom<typeof uzsakymai.$inferSelect>(),
        404: errorSchemas.notFound,
      },
    },
    delete: {
      method: 'DELETE' as const,
      path: '/api/uzsakymai/:id' as const,
      responses: {
        204: z.void(),
      },
    }
  },
  gaminiai: {
    list: {
      method: 'GET' as const,
      path: '/api/gaminiai' as const,
      input: z.object({
        uzsakymo_id: z.string().optional(),
      }).optional(),
      responses: {
        200: z.array(z.custom<typeof gaminiai.$inferSelect>()),
      },
    },
    create: {
      method: 'POST' as const,
      path: '/api/gaminiai' as const,
      input: insertGaminysSchema,
      responses: {
        201: z.custom<typeof gaminiai.$inferSelect>(),
        400: errorSchemas.validation,
      },
    },
  },
  uzsakovai: {
    list: {
      method: 'GET' as const,
      path: '/api/uzsakovai' as const,
      responses: {
        200: z.array(z.custom<typeof uzsakovai.$inferSelect>()),
      },
    }
  },
  objektai: {
    list: {
      method: 'GET' as const,
      path: '/api/objektai' as const,
      responses: {
        200: z.array(z.custom<typeof objektai.$inferSelect>()),
      },
    }
  },
  gaminio_tipai: {
    list: {
      method: 'GET' as const,
      path: '/api/gaminio_tipai' as const,
      responses: {
        200: z.array(z.custom<typeof gaminio_tipai.$inferSelect>()),
      },
    }
  }
};

export function buildUrl(path: string, params?: Record<string, string | number>): string {
  let url = path;
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (url.includes(`:${key}`)) {
        url = url.replace(`:${key}`, String(value));
      }
    });
  }
  return url;
}
