import { useProducts } from "@/hooks/use-products";
import { Layout } from "@/components/layout";
import { Input } from "@/components/ui/input";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Search, Loader2, Package } from "lucide-react";
import { useState } from "react";

export default function ProductsPage() {
  const { data: products, isLoading } = useProducts();
  const [search, setSearch] = useState("");

  const filteredProducts = products?.filter((p) =>
    p.gaminio_numeris?.toLowerCase().includes(search.toLowerCase()) ||
    p.protokolo_nr?.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <Layout>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 font-display">Gaminiai</h1>
        <p className="text-muted-foreground mt-1">Visų sistemos gaminių sąrašas</p>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        <div className="p-4 border-b border-border bg-slate-50/50">
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Ieškoti pagal numerį ar protokolą..."
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
        ) : filteredProducts?.length === 0 ? (
          <div className="p-12 text-center text-muted-foreground">
            <Package className="h-10 w-10 mx-auto mb-3 opacity-50" />
            <p>Gaminių nerasta</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader className="bg-slate-50">
                <TableRow>
                  <TableHead className="font-semibold text-gray-900">Gaminio Nr.</TableHead>
                  <TableHead className="font-semibold text-gray-900">Protokolo Nr.</TableHead>
                  <TableHead className="font-semibold text-gray-900">Atitikmuo</TableHead>
                  <TableHead className="font-semibold text-gray-900">Užsakymo ID</TableHead>
                  <TableHead className="font-semibold text-gray-900">Tipas ID</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredProducts?.map((product) => (
                  <TableRow key={product.id} className="hover:bg-slate-50 transition-colors">
                    <TableCell className="font-medium">{product.gaminio_numeris}</TableCell>
                    <TableCell>{product.protokolo_nr}</TableCell>
                    <TableCell>{product.atitikmuo_kodas}</TableCell>
                    <TableCell>{product.uzsakymo_id}</TableCell>
                    <TableCell>{product.gaminio_tipas_id}</TableCell>
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
