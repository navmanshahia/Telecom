import { NextRequest, NextResponse } from "next/server";
import { allowedStatuses, customers, CustomerStatus } from "@/lib/customer-store";

export async function GET() {
  return NextResponse.json({ customers });
}

export async function PATCH(request: NextRequest) {
  const body = await request.json();
  const id = Number(body.id);
  const status = String(body.status) as CustomerStatus;
  if (!Number.isInteger(id) || !allowedStatuses.includes(status)) {
    return NextResponse.json({ error: "Invalid request" }, { status: 400 });
  }
  const customer = customers.find((item) => item.id === id);
  if (!customer) return NextResponse.json({ error: "Customer not found" }, { status: 404 });
  customer.status = status;
  return NextResponse.json({ customer });
}
