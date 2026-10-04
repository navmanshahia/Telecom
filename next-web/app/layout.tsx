import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "SecureLink | Compare TELUS & Rogers Offers",
  description:
    "Compare TELUS and Rogers internet, mobility and bundle offers in a SecureLink side-by-side experience.",
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
