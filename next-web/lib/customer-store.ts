export type CustomerStatus = "pending" | "approved" | "rejected" | "suspended" | "blocked";
export type Customer = { id: number; name: string; email: string; phone: string; status: CustomerStatus; createdAt: string };

const g = globalThis as typeof globalThis & { secureLinkCustomers?: Customer[] };
if (!g.secureLinkCustomers) {
  g.secureLinkCustomers = [
    { id: 1, name: "Demo Pending Customer", email: "pending@example.com", phone: "", status: "pending", createdAt: new Date().toISOString() }
  ];
}
export const customers = g.secureLinkCustomers;
export const allowedStatuses: CustomerStatus[] = ["pending","approved","rejected","suspended","blocked"];
