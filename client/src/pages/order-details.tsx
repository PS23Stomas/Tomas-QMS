import { useOrder, useDeleteOrder } from "@/hooks/use-orders";
import { useProducts, useCreateProduct, useProductTypes } from "@/hooks/use-products";
import { useRoute, useLocation } from "wouter";
import { Layout } from "@/components/layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { Input } from "@/components/ui/input";
import { 
  ArrowLeft, 
  Trash2, 
  Plus, 
  FileText, 
  Loader2, 
  Box, 
  Calendar, 
  Hash, 
  User 
} from "lucide-react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { insertGaminysSchema } from "@shared/schema";
import { z } from "zod";
import { useState } from "react";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

export default function OrderDetailsPage() {
  const [match, params] = useRoute("/uzsakymai/:id");
  const [, setLocation] = useLocation();
  const orderId = Number(params?.id);
  
  const { data: order, isLoading: isOrderLoading } = useOrder(orderId);
  const { data: products, isLoading: isProductsLoading } = useProducts(String(orderId));
  const { mutate: deleteOrder } = useDeleteOrder();

  if (!match) return null;

  const handleDelete = () => {
    deleteOrder(orderId, {
      onSuccess: () => setLocation("/uzsakymai")
    });
  };

  if (isOrderLoading) {
    return (
      <Layout>
        <div className="h-[80vh] flex items-center justify-center">
          <Loader2 className="h-12 w-12 animate-spin text-primary" />
        </div>
      </Layout>
    );
  }

  if (!order) {
    return (
      <Layout>
        <div className="text-center py-20">
          <h2 className="text-2xl font-bold text-gray-900">Užsakymas nerastas</h2>
          <Button className="mt-4" onClick={() => setLocation("/uzsakymai")}>
            Grįžti į sąrašą
          </Button>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="mb-8">
        <Button variant="ghost" className="pl-0 gap-2 mb-4 hover:bg-transparent hover:text-primary" onClick={() => setLocation("/uzsakymai")}>
          <ArrowLeft className="h-4 w-4" />
          Grįžti į užsakymus
        </Button>
        
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-3xl font-bold text-gray-900 font-display">Užsakymas {order.uzsakymo_numeris}</h1>
              <span className="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm font-medium">
                Aktyvus
              </span>
            </div>
            <p className="text-muted-foreground mt-1">Sukurta {order.sukurtas}</p>
          </div>
          
          <div className="flex gap-2">
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="outline" className="text-destructive border-destructive/20 hover:bg-destructive/10 hover:text-destructive">
                  <Trash2 className="h-4 w-4 mr-2" />
                  Ištrinti
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Ar tikrai norite ištrinti?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Šis veiksmas negali būti atšauktas. Tai visam laikui pašalins užsakymą ir visus susijusius duomenis.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Atšaukti</AlertDialogCancel>
                  <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">Ištrinti</AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
            <Button className="btn-primary-gradient">
              <FileText className="h-4 w-4 mr-2" />
              Generuoti ataskaitą
            </Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <InfoCard icon={Hash} label="Kiekis" value={`${order.kiekis} vnt.`} />
        <InfoCard icon={Calendar} label="Sukurtas" value={order.sukurtas} />
        <InfoCard icon={User} label="Vartotojas ID" value={order.vartotojas_id?.toString() || "-"} />
        <InfoCard icon={Box} label="Objektas ID" value={order.objektas_id?.toString() || "-"} />
      </div>

      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <h2 className="text-xl font-bold text-gray-900 font-display">Gaminiai ({products?.length || 0})</h2>
          <AddProductDialog orderId={orderId} />
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
          {isProductsLoading ? (
            <div className="p-12 flex justify-center">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          ) : products?.length === 0 ? (
            <div className="p-12 text-center text-muted-foreground">
              <Box className="h-10 w-10 mx-auto mb-3 opacity-50" />
              <p>Šiame užsakyme gaminių dar nėra.</p>
            </div>
          ) : (
            <Table>
              <TableHeader className="bg-slate-50">
                <TableRow>
                  <TableHead>Gaminio Nr.</TableHead>
                  <TableHead>Protokolo Nr.</TableHead>
                  <TableHead>Atitikmuo Kodas</TableHead>
                  <TableHead>Gaminio Tipas ID</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {products?.map((product) => (
                  <TableRow key={product.id} className="hover:bg-slate-50">
                    <TableCell className="font-medium">{product.gaminio_numeris}</TableCell>
                    <TableCell>{product.protokolo_nr}</TableCell>
                    <TableCell>{product.atitikmuo_kodas}</TableCell>
                    <TableCell>{product.gaminio_tipas_id}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </div>
      </div>
    </Layout>
  );
}

function InfoCard({ icon: Icon, label, value }: { icon: any, label: string, value: string }) {
  return (
    <Card className="shadow-sm hover:shadow-md transition-shadow">
      <CardContent className="p-6 flex items-center gap-4">
        <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
          <Icon className="h-5 w-5" />
        </div>
        <div>
          <p className="text-sm text-muted-foreground font-medium">{label}</p>
          <p className="text-lg font-bold text-gray-900">{value}</p>
        </div>
      </CardContent>
    </Card>
  );
}

function AddProductDialog({ orderId }: { orderId: number }) {
  const [open, setOpen] = useState(false);
  const { mutate: createProduct, isPending } = useCreateProduct();
  const { data: types } = useProductTypes();

  const formSchema = insertGaminysSchema.extend({
    uzsakymo_id: z.coerce.number(),
    gaminio_tipas_id: z.coerce.number(),
  });

  const form = useForm<z.infer<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      uzsakymo_id: orderId,
      gaminio_numeris: "",
      protokolo_nr: "",
      atitikmuo_kodas: "",
    },
  });

  const onSubmit = (data: z.infer<typeof formSchema>) => {
    createProduct(data, {
      onSuccess: () => {
        setOpen(false);
        form.reset();
      }
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" className="bg-primary hover:bg-primary/90">
          <Plus className="h-4 w-4 mr-2" />
          Pridėti gaminį
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Pridėti naują gaminį</DialogTitle>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="gaminio_numeris"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Gaminio numeris</FormLabel>
                  <FormControl>
                    <Input placeholder="PVZ: G-001" {...field} value={field.value || ''} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="protokolo_nr"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Protokolo numeris</FormLabel>
                  <FormControl>
                    <Input placeholder="PVZ: P-123" {...field} value={field.value || ''} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="atitikmuo_kodas"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Atitikmens kodas</FormLabel>
                  <FormControl>
                    <Input placeholder="PVZ: K-99" {...field} value={field.value || ''} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="gaminio_tipas_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Gaminio tipas</FormLabel>
                  <Select onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Pasirinkite tipą" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {types?.map((type) => (
                        <SelectItem key={type.id} value={String(type.id)}>
                          {type.gaminio_tipas} ({type.grupe})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <div className="flex justify-end gap-3 pt-4">
              <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                Atšaukti
              </Button>
              <Button type="submit" disabled={isPending}>
                {isPending ? "Pridedama..." : "Pridėti"}
              </Button>
            </div>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
