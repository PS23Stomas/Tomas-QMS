import { useOrders, useCreateOrder } from "@/hooks/use-orders";
import { useClients, useObjects } from "@/hooks/use-references";
import { Layout } from "@/components/layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Plus, Search, Loader2, Package } from "lucide-react";
import { useState } from "react";
import { Link } from "wouter";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { insertUzsakymasSchema } from "@shared/schema";
import { z } from "zod";
import { useAuth } from "@/hooks/use-auth";

export default function OrdersPage() {
  const { data: orders, isLoading } = useOrders();
  const [search, setSearch] = useState("");
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  const filteredOrders = orders?.filter((order) =>
    order.uzsakymo_numeris?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <Layout>
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 font-display">Užsakymai</h1>
          <p className="text-muted-foreground mt-1">Valdykite visus užsakymus vienoje vietoje</p>
        </div>
        <div className="flex gap-2 w-full sm:w-auto">
          <CreateOrderDialog open={isCreateOpen} onOpenChange={setIsCreateOpen} />
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div className="p-4 border-b border-border flex gap-4 bg-slate-50/50">
          <div className="relative flex-1 max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Ieškoti pagal numerį..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="pl-9 bg-white"
            />
          </div>
        </div>

        {isLoading ? (
          <div className="p-12 flex justify-center">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : filteredOrders?.length === 0 ? (
          <div className="p-12 text-center text-muted-foreground flex flex-col items-center">
            <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mb-4">
              <Package className="h-6 w-6 text-slate-400" />
            </div>
            <p className="font-medium">Užsakymų nerasta</p>
            <p className="text-sm mt-1">Pabandykite pakeisti paiešką arba sukurkite naują užsakymą.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="bg-slate-50">
                <TableRow>
                  <TableHead className="font-semibold text-gray-900">Užsakymo Nr.</TableHead>
                  <TableHead className="font-semibold text-gray-900">Sukurtas</TableHead>
                  <TableHead className="font-semibold text-gray-900">Kiekis</TableHead>
                  <TableHead className="font-semibold text-gray-900">Užsakovas</TableHead>
                  <TableHead className="font-semibold text-gray-900">Objektas</TableHead>
                  <TableHead className="text-right">Veiksmai</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredOrders?.map((order) => (
                  <TableRow key={order.id} className="hover:bg-slate-50 transition-colors">
                    <TableCell className="font-medium">{order.uzsakymo_numeris}</TableCell>
                    <TableCell className="text-muted-foreground">{order.sukurtas}</TableCell>
                    <TableCell>
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {order.kiekis || 0} vnt.
                      </span>
                    </TableCell>
                    <TableCell>{order.uzsakovas_id}</TableCell>
                    <TableCell>{order.objektas_id}</TableCell>
                    <TableCell className="text-right">
                      <Link href={`/uzsakymai/${order.id}`}>
                        <Button variant="outline" size="sm" className="hover:bg-primary hover:text-white transition-colors">
                          Peržiūrėti
                        </Button>
                      </Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </div>
    </Layout>
  );
}

function CreateOrderDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const { mutate: createOrder, isPending } = useCreateOrder();
  const { data: clients } = useClients();
  const { data: objects } = useObjects();
  const { user } = useAuth();
  
  // Extend schema for form validation
  const formSchema = insertUzsakymasSchema.extend({
    uzsakovas_id: z.coerce.number(),
    objektas_id: z.coerce.number(),
    kiekis: z.coerce.number().min(1, "Kiekis privalo būti bent 1"),
  });

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      uzsakymo_numeris: "",
      kiekis: 1,
      vartotojas_id: user?.id,
      gaminiu_rusis_id: 1, // Default value as per schema
    },
  });

  const onSubmit = (data: z.infer<typeof formSchema>) => {
    createOrder(data, {
      onSuccess: () => {
        onOpenChange(false);
        form.reset();
      }
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogTrigger asChild>
        <Button className="btn-primary-gradient gap-2">
          <Plus className="h-4 w-4" />
          Naujas užsakymas
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Sukurti naują užsakymą</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="uzsakymo_numeris"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Užsakymo numeris</FormLabel>
                  <FormControl>
                    <Input placeholder="PVZ: 2024-001" {...field} value={field.value || ''} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="kiekis"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Kiekis</FormLabel>
                  <FormControl>
                    <Input type="number" min="1" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="uzsakovas_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Užsakovas</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={String(field.value)}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Pasirinkite užsakovą" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {clients?.map((client) => (
                        <SelectItem key={client.id} value={String(client.id)}>
                          {client.uzsakovas}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="objektas_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Objektas</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={String(field.value)}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Pasirinkite objektą" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {objects?.map((obj) => (
                        <SelectItem key={obj.id} value={String(obj.id)}>
                          {obj.pavadinimas}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <div className="flex justify-end gap-3 pt-4">
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                Atšaukti
              </Button>
              <Button type="submit" disabled={isPending} className="bg-primary hover:bg-primary/90">
                {isPending ? "Kuriama..." : "Sukurti"}
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
