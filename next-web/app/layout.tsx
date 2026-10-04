import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "SecureLink | Private Telecom Access",
  description:
    "A premium private telecom experience for internet, mobility, TV, security, referrals and order tracking.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
