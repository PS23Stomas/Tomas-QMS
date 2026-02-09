import { useClients } from "@/hooks/use-references";
import { Layout } from "@/components/layout";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Loader2, Users } from "lucide-react";

export default function ClientsPage() {
  const { data: clients, isLoading } = useClients();

  return (
    <Layout>
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 font-display">Užsakovai</h1>
        <p className="text-muted-foreground mt-1">Partnerių sąrašas</p>
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
        {isLoading ? (
          <div className="p-12 flex justify-center">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : clients?.length === 0 ? (
          <div className="p-12 text-center text-muted-foreground">
            <Users className="h-10 w-10 mx-auto mb-3 opacity-50" />
            <p>Užsakovų nerasta</p>
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
              {clients?.map((client) => (
                <TableRow key={client.id} className="hover:bg-slate-50 transition-colors">
                  <TableCell className="font-medium w-20">{client.id}</TableCell>
                  <TableCell>{client.uzsakovas}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>
    </Layout>
  );
}
