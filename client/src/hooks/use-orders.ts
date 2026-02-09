import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, buildUrl } from "@shared/routes";
import { z } from "zod";
import { useToast } from "@/hooks/use-toast";

type InsertUzsakymas = z.infer<typeof api.uzsakymai.create.input>;

export function useOrders() {
  return useQuery({
    queryKey: [api.uzsakymai.list.path],
    queryFn: async () => {
      const res = await fetch(api.uzsakymai.list.path, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch orders");
      return api.uzsakymai.list.responses[200].parse(await res.json());
    },
  });
}

export function useOrder(id: number) {
  return useQuery({
    queryKey: [api.uzsakymai.get.path, id],
    queryFn: async () => {
      const url = buildUrl(api.uzsakymai.get.path, { id });
      const res = await fetch(url, { credentials: "include" });
      if (res.status === 404) return null;
      if (!res.ok) throw new Error("Failed to fetch order");
      return api.uzsakymai.get.responses[200].parse(await res.json());
    },
    enabled: !!id,
  });
}

export function useCreateOrder() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  return useMutation({
    mutationFn: async (data: InsertUzsakymas) => {
      const res = await fetch(api.uzsakymai.create.path, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
        credentials: "include",
      });
      if (!res.ok) throw new Error("Nepavyko sukurti užsakymo");
      return api.uzsakymai.create.responses[201].parse(await res.json());
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [api.uzsakymai.list.path] });
      toast({
        title: "Sėkmė",
        description: "Užsakymas sėkmingai sukurtas",
      });
    },
    onError: () => {
      toast({
        variant: "destructive",
        title: "Klaida",
        description: "Įvyko klaida kuriant užsakymą",
      });
    }
  });
}

export function useDeleteOrder() {
  const queryClient = useQueryClient();
  const { toast } = useToast();

  return useMutation({
    mutationFn: async (id: number) => {
      const url = buildUrl(api.uzsakymai.delete.path, { id });
      const res = await fetch(url, { 
        method: "DELETE",
        credentials: "include" 
      });
      if (!res.ok) throw new Error("Nepavyko ištrinti užsakymo");
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [api.uzsakymai.list.path] });
      toast({
        title: "Ištrinta",
        description: "Užsakymas sėkmingai pašalintas",
      });
    },
  });
}
