import { Link, useLocation } from "wouter";
import { useAuth } from "@/hooks/use-auth";
import {
  LayoutDashboard,
  Package,
  Users,
  Building2,
  LogOut,
  Menu,
  X
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { useState } from "react";

const NAV_ITEMS = [
  { label: "Užsakymai", icon: LayoutDashboard, href: "/uzsakymai" },
  { label: "Gaminiai", icon: Package, href: "/gaminiai" },
  { label: "Užsakovai", icon: Users, href: "/uzsakovai" },
  { label: "Objektai", icon: Building2, href: "/objektai" },
];

export function Layout({ children }: { children: React.ReactNode }) {
  const [location] = useLocation();
  const { logout, user } = useAuth();
  const [open, setOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background flex flex-col md:flex-row">
      {/* Mobile Header */}
      <header className="md:hidden flex items-center justify-between p-4 bg-white border-b border-border">
        <h1 className="text-xl font-bold text-primary font-display">MT Eksportas</h1>
        <Sheet open={open} onOpenChange={setOpen}>
          <SheetTrigger asChild>
            <Button variant="ghost" size="icon">
              <Menu className="h-6 w-6" />
            </Button>
          </SheetTrigger>
          <SheetContent side="left" className="w-[300px] sm:w-[400px]">
            <nav className="flex flex-col gap-4 mt-8">
              {NAV_ITEMS.map((item) => (
                <Link key={item.href} href={item.href} onClick={() => setOpen(false)}>
                  <div className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                    location.startsWith(item.href) 
                      ? "bg-primary/10 text-primary font-medium" 
                      : "text-muted-foreground hover:bg-muted hover:text-foreground"
                  }`}>
                    <item.icon className="w-5 h-5" />
                    {item.label}
                  </div>
                </Link>
              ))}
              <Button 
                variant="ghost" 
                className="justify-start gap-3 px-4 mt-auto text-destructive hover:text-destructive hover:bg-destructive/10"
                onClick={() => logout()}
              >
                <LogOut className="w-5 h-5" />
                Atsijungti
              </Button>
            </nav>
          </SheetContent>
        </Sheet>
      </header>

      {/* Desktop Sidebar */}
      <aside className="hidden md:flex flex-col w-72 bg-white border-r border-border h-screen sticky top-0 p-6 shadow-sm z-10">
        <div className="mb-10 flex items-center gap-3 px-2">
          <div className="w-10 h-10 rounded-xl bg-primary flex items-center justify-center text-white font-bold text-xl font-display shadow-lg shadow-primary/25">
            MT
          </div>
          <div>
            <h1 className="font-bold text-lg leading-tight font-display">MT Eksportas</h1>
            <p className="text-xs text-muted-foreground">Valdymo sistema</p>
          </div>
        </div>

        <nav className="flex flex-col gap-2 flex-1">
          {NAV_ITEMS.map((item) => (
            <Link key={item.href} href={item.href}>
              <div className={`flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 cursor-pointer ${
                location.startsWith(item.href)
                  ? "bg-primary text-primary-foreground shadow-md shadow-primary/20 font-medium translate-x-1"
                  : "text-muted-foreground hover:bg-muted hover:text-foreground hover:translate-x-1"
              }`}>
                <item.icon className="w-5 h-5" />
                {item.label}
              </div>
            </Link>
          ))}
        </nav>

        <div className="mt-auto pt-6 border-t border-border">
          <div className="flex items-center gap-3 px-4 mb-4">
            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold border border-slate-200">
              {user?.vardas?.[0]}
            </div>
            <div className="overflow-hidden">
              <p className="text-sm font-medium truncate">{user?.vardas}</p>
              <p className="text-xs text-muted-foreground truncate">{user?.el_pastas}</p>
            </div>
          </div>
          <Button 
            variant="ghost" 
            className="w-full justify-start gap-3 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
            onClick={() => logout()}
          >
            <LogOut className="w-4 h-4" />
            Atsijungti
          </Button>
        </div>
      </aside>

      {/* Main Content */}
      <main className="flex-1 p-4 md:p-8 lg:p-10 overflow-y-auto bg-slate-50/50">
        <div className="max-w-7xl mx-auto animate-enter">
          {children}
        </div>
      </main>
    </div>
  );
}
