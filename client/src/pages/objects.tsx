import { useObjects } from "@/hooks/use-references";
import { Layout } from "@/components/layout";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Loader2, Building2 } from "lucide-react";

export default function ObjectsPage() {
  const { data: objects, isLoading } = useObjects();

  return (
    <Layout>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 font-display">Objektai</h1>
        <p className="text-muted-foreground mt-1">Registruoti objektai</p>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        {isLoading ? (
          <div className="p-12 flex justify-center">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : objects?.length === 0 ? (
          <div className="p-12 text-center text-muted-foreground">
            <Building2 className="h-10 w-10 mx-auto mb-3 opacity-50" />
            <p>Objektų nerasta</p>
          </div>
        ) : (
          <Table>
            <TableHeader className="bg-slate-50">
              <TableRow>
                <TableHead className="font-semibold text-gray-900">ID</TableHead>
                <TableHead className="font-semibold text-gray-900">Pavadinimas</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {objects?.map((obj) => (
                <TableRow key={obj.id} className="hover:bg-slate-50 transition-colors">
                  <TableCell className="font-medium w-20">{obj.id}</TableCell>
                  <TableCell>{obj.pavadinimas}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>
    </Layout>
  );
}
