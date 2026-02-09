import { useQuery } from "@tanstack/react-query";
import { api } from "@shared/routes";

export function useClients() {
  return useQuery({
    queryKey: [api.uzsakovai.list.path],
    queryFn: async () => {
      const res = await fetch(api.uzsakovai.list.path, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch clients");
      return api.uzsakovai.list.responses[200].parse(await res.json());
    },
  });
}

export function useObjects() {
  return useQuery({
    queryKey: [api.objektai.list.path],
    queryFn: async () => {
      const res = await fetch(api.objektai.list.path, { credentials: "include" });
      if (!res.ok) throw new Error("Failed to fetch objects");
      return api.objektai.list.responses[200].parse(await res.json());
    },
  });
}
