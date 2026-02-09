import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, buildUrl } from "@shared/routes";
import { z } from "zod";
import { useToast } from "@/hooks/use-toast";

type InsertGaminys = z.infer<typeof api.gaminiai.create.input>;

export function useProducts(uzsakymo_id?: string) {
  return useQuery({
    queryKey: [api.gaminiai.list.path, { uzsakymo_id }],
    queryFn: async () => {
      const url = new URL(api.gaminiai.list.path, window.location.origin);
      if (uzsakymo_id) url.searchParams.append("uzsakymo_id", uzsakymo_id);
      
      const res = await fetch(url.toString(), { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch products");
      return api.gaminiai.list.responses[200].parse(await res.json());
    },
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  return useMutation({
    mutationFn: async (data: InsertGaminys) => {
      const res = await fetch(api.gaminiai.create.path, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
        credentials: "include",
      });
      if (!res.ok) throw new Error("Nepavyko sukurti gaminio");
      return api.gaminiai.create.responses[201].parse(await res.json());
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [api.gaminiai.list.path] });
      toast({
        title: "Sėkmė",
        description: "Gaminys sėkmingai pridėtas",
      });
    },
    onError: () => {
      toast({
        variant: "destructive",
        title: "Klaida",
        description: "Įvyko klaida pridedant gaminį",
      });
    }
  });
}

export function useProductTypes() {
  return useQuery({
    queryKey: [api.gaminio_tipai.list.path],
    queryFn: async () => {
      const res = await fetch(api.gaminio_tipai.list.path, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch product types");
      return api.gaminio_tipai.list.responses[200].parse(await res.json());
    },
  });
}
