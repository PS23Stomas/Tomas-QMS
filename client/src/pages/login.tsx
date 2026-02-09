import { useAuth } from "@/hooks/use-auth";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useLocation } from "wouter";
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Loader2 } from "lucide-react";
import { useEffect } from "react";

const loginSchema = z.object({
  el_pastas: z.string().email("Neteisingas el. pašto formatas"),
  slaptazodis: z.string().min(1, "Įveskite slaptažodį"),
});

export default function LoginPage() {
  const { login, isLoggingIn, user } = useAuth();
  const [, setLocation] = useLocation();

  useEffect(() => {
    if (user) {
      setLocation("/uzsakymai");
    }
  }, [user, setLocation]);

  const form = useForm<z.infer<typeof loginSchema>>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      el_pastas: "",
      slaptazodis: "",
    },
  });

  function onSubmit(values: z.infer<typeof loginSchema>) {
    login(values);
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-100 p-4 relative overflow-hidden">
      {/* Background decoration */}
      <div className="absolute top-0 left-0 w-full h-1/2 bg-primary/10 -skew-y-6 transform origin-top-left translate-y-[-20%]"></div>
      
      <Card className="w-full max-w-md shadow-2xl border-0 z-10">
        <CardHeader className="space-y-3 pb-8 text-center pt-8">
          <div className="mx-auto w-16 h-16 rounded-2xl bg-primary flex items-center justify-center text-white font-bold text-3xl font-display shadow-lg shadow-primary/30 mb-2">
            MT
          </div>
          <CardTitle className="text-2xl font-bold font-display">MT Eksportas</CardTitle>
          <CardDescription className="text-base">Prisijunkite prie sistemos</CardDescription>
        </CardHeader>
        <CardContent className="pb-8 px-8">
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
              <FormField
                control={form.control}
                name="el_pastas"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>El. paštas</FormLabel>
                    <FormControl>
                      <Input 
                        placeholder="vardas@imone.lt" 
                        {...field} 
                        className="h-12 bg-slate-50 border-slate-200 focus:bg-white transition-all duration-200"
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="slaptazodis"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Slaptažodis</FormLabel>
                    <FormControl>
                      <Input 
                        type="password" 
                        placeholder="••••••••" 
                        {...field} 
                        className="h-12 bg-slate-50 border-slate-200 focus:bg-white transition-all duration-200"
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <Button 
                type="submit" 
                className="w-full h-12 text-base font-semibold btn-primary-gradient mt-4" 
                disabled={isLoggingIn}
              >
                {isLoggingIn ? (
                  <>
                    <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                    Jungiamasi...
                  </>
                ) : (
                  "Prisijungti"
                )}
              </Button>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}
