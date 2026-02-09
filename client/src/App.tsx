import { Switch, Route, Redirect } from "wouter";
import { queryClient } from "./lib/queryClient";
import { QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import NotFound from "@/pages/not-found";
import LoginPage from "@/pages/login";
import OrdersPage from "@/pages/orders";
import OrderDetailsPage from "@/pages/order-details";
import ProductsPage from "@/pages/products";
import ClientsPage from "@/pages/clients";
import ObjectsPage from "@/pages/objects";
import { useAuth } from "@/hooks/use-auth";
import { Loader2 } from "lucide-react";

function PrivateRoute({ component: Component }: { component: React.ComponentType }) {
  const { user, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <Loader2 className="h-8 w-8 animate-spin text-primary" />
      </div>
    );
  }

  if (!user) {
    return <Redirect to="/login" />;
  }

  return <Component />;
}

function Router() {
  return (
    <Switch>
      <Route path="/login" component={LoginPage} />
      
      <Route path="/uzsakymai">
        <PrivateRoute component={OrdersPage} />
      </Route>
      <Route path="/uzsakymai/:id">
        <PrivateRoute component={OrderDetailsPage} />
      </Route>
      
      <Route path="/gaminiai">
        <PrivateRoute component={ProductsPage} />
      </Route>
      
      <Route path="/uzsakovai">
        <PrivateRoute component={ClientsPage} />
      </Route>
      
      <Route path="/objektai">
        <PrivateRoute component={ObjectsPage} />
      </Route>

      <Route path="/">
        <PrivateRoute component={() => <Redirect to="/uzsakymai" />} />
      </Route>

      <Route component={NotFound} />
    </Switch>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <Toaster />
        <Router />
      </TooltipProvider>
    </QueryClientProvider>
  );
}

export default App;
