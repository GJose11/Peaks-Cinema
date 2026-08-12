# 🎬 PeaksCinema ERD with Relationship Lines
## Visual Database Connections for Professor Presentation

---

## 🔗 **COMPLETE ERD WITH RELATIONSHIP LINES**

```
╔════════════════════════════════════════════════════════════════════════════════╗
║                                    🏢 MALL                                      ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Mall_ID (PK)                                                             ║  ║
║  ║  MallName                                                                  ║  ║
║  ║  Location                                                                  ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
║           │                                                                   ║
║           │ 1:N (One mall has many theaters)                                 ║
║           ▼                                                                   ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║                                 🎭 THEATER                              ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║  Theater_ID (PK)                                                   ║  ║  ║
║  ║  ║  Mall_ID (FK) ←────────────────────────────────────────────────────╩  ║  ║
║  ║  ║  TheaterName                                                       ║  ║  ║
║  ║  ║  TotalSeats                                                        ║  ║  ║
║  ║  ║  TheaterType                                                       ║  ║  ║
║  ║  ╚═════════════════════════════════════════════════════════════════╝  ║  ║
║  ║           │                                                            ║  ║
║  ║           ├─── 1:N (One theater has many seats)                        ║  ║
║  ║           │                                                            ║  ║
║  ║           └─── 1:N (One theater has many showtimes)                   ║  ║
║  ║           │                                                            ║  ║
║  ║           ▼                                                            ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║  ╔════════════════════════════════════════════════════════╗   ║  ║  ║
║  ║  ║  ║                    💺 SEATS                            ║   ║  ║  ║
║  ║  ║  ║  ╔═════════════════════════════════════════════════╗ ║   ║  ║  ║
║  ║  ║  ║  ║  Seat_ID (PK)                                     ║ ║   ║  ║  ║
║  ║  ║  ║  ║  Theater_ID (FK) ←─────────────────────────────────╫─╫───╫──╫──╫
║  ║  ║  ║  ║  TimeSlot_ID (FK)                                 ║ ║   ║  ║  ║
║  ║  ║  ║  ║  SeatRow                                          ║ ║   ║  ║  ║
║  ║  ║  ║  ║  SeatColumn                                       ║ ║   ║  ║  ║
║  ║  ║  ║  ║  SeatType                                         ║ ║   ║  ║  ║
║  ║  ║  ║  ║  SeatAvailability                                 ║ ║   ║  ║  ║
║  ║  ║  ║  ╚═════════════════════════════════════════════════╝ ║   ║  ║  ║
║  ║  ║  ╚════════════════════════════════════════════════════════╝   ║  ║  ║
║  ║  ║           │                                                  ║  ║  ║
║  ║  ║           └─── 1:N (One timeslot has many seats)             ║  ║  ║
║  ║  ╚═══════════════════════════════════════════════════════════════╝  ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                                    🎬 MOVIE                                     ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Movie_ID (PK)                                                            ║  ║
║  ║  MovieName                                                                ║  ║
║  ║  MovieDescription                                                         ║  ║
║  ║  Genre                                                                    ║  ║
║  ║  Rating                                                                   ║  ║
║  ║  Runtime                                                                  ║  ║
║  ║  MoviePoster                                                              ║  ║
║  ║  MovieAvailability                                                        ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
║           │                                                                   ║
║           │ 1:N (One movie has many showtimes)                                 ║
║           ▼                                                                   ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║                                 📅 TIMESLOT                            ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║  TimeSlot_ID (PK)                                                  ║  ║  ║
║  ║  ║  Movie_ID (FK) ←──────────────────────────────────────────────────╫──╫──╫
║  ║  ║  Theater_ID (FK) ←─────────────────────────────────────────────────╫──╫──╫
║  ║  ║  StartTime                                                         ║  ║  ║
║  ║  ║  EndTime                                                           ║  ║  ║
║  ║  ║  Date                                                              ║  ║  ║
║  ║  ║  ScreeningType                                                     ║  ║  ║
║  ║  ╚═════════════════════════════════════════════════════════════════╝  ║  ║
║  ║           │                                                            ║  ║
║  ║           ├─── 1:N (One timeslot has many seats) ──────────────────────╫──╫──╫
║  ║           │                                                            ║  ║
║  ║           ├─── 1:N (One timeslot has many tickets)                    ║  ║
║  ║           │                                                            ║  ║
║  ║           └─── 1:N (One timeslot has many food orders)                ║  ║
║  ║           │                                                            ║  ║
║  ║           ▼                                                            ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║                    🎟️ TICKET                                ║   ║  ║  ║
║  ║  ║  ╔════════════════════════════════════════════════════════╗ ║   ║  ║  ║
║  ║  ║  ║  Ticket_ID (PK)                                         ║ ║   ║  ║  ║
║  ║  ║  ║  Seat_ID (FK) ←──────────────────────────────────────────╫─╫───╫──╫──╫
║  ║  ║  ║  Customer_ID (FK) ←───────────────────────────────────────╫─╫───╫──╫──╫
║  ║  ║  ║  Movie_ID (FK) ←──────────────────────────────────────────╫─╫───╫──╫──╫
║  ║  ║  ║  TimeSlot_ID (FK) ←───────────────────────────────────────╫─╫───╫──╫──╫
║  ║  ║  ║  Price                                                   ║ ║   ║  ║  ║
║  ║  ║  ║  Status                                                  ║ ║   ║  ║  ║
║  ║  ║  ║  DateTime                                                ║ ║   ║  ║  ║
║  ║  ║  ║  ╚══════════════════════════════════════════════════════╝ ║   ║  ║  ║
║  ║  ║  ╚═════════════════════════════════════════════════════════════╝   ║  ║  ║
║  ║  ║           │                                                      ║  ║  ║
║  ║  ║           └─── 1:1 (Each ticket has exactly one payment)          ║  ║  ║
║  ║  ╚═════════════════════════════════════════════════════════════════╝  ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                                   👤 CUSTOMER                                 ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Customer_ID (PK)                                                        ║  ║
║  ║  Name                                                                     ║  ║
║  ║  Email                                                                    ║  ║
║  ║  PhoneNumber                                                              ║  ║
║  ║  CountryCode                                                              ║  ║
║  ║  PaymentMethod                                                            ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
║           │                                                                   ║
║           ├─── 1:N (One customer has many tickets) ───────────────────────────╫──╫
║           │                                                                   ║
║           ├─── 1:N (One customer has many notifications)                       ║
║           │                                                                   ║
║           ├─── 1:N (One customer has many food orders)                         ║
║           │                                                                   ║
║           ├─── 1:N (One customer has many reminders)                           ║
║           │                                                                   ║
║           └─── 1:N (One customer has many payments)                           ║
║           │                                                                   ║
║           ▼                                                                   ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║                                 🔔 NOTIFICATIONS                        ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║  Notif_ID (PK)                                                     ║  ║  ║
║  ║  ║  Customer_ID (FK) ←───────────────────────────────────────────────╫──╫──╫
║  ║  ║  Title                                                             ║  ║  ║
║  ║  ║  Message                                                           ║  ║  ║
║  ║  ║  Type                                                              ║  ║  ║
║  ║  ║  IsRead                                                            ║  ║  ║
║  ║  ║  Created_At                                                        ║  ║  ║
║  ║  ╚═════════════════════════════════════════════════════════════════╝  ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                                   💳 PAYMENT                                   ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Payment_ID (PK)                                                          ║  ║
║  ║  Ticket_ID (FK) ←─────────────────────────────────────────────────────────╫──╫
║  ║  PaymentMethod                                                            ║  ║
║  ║  AmountPaid                                                               ║  ║
║  ║  PaymentDate                                                              ║  ║
║  ║  PaymentStatus                                                            ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
║           │                                                                   ║
║           │ 1:1 (Each payment has exactly one receipt)                        ║
║           ▼                                                                   ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║                                 🧾 E-RECEIPT                          ║  ║
║  ║  ╔═════════════════════════════════════════════════════════════════╗  ║  ║
║  ║  ║  Receipt_ID (PK)                                                   ║  ║  ║
║  ║  ║  PaymentID (FK) ←──────────────────────────────────────────────────╫──╫──╫
║  ║  ║  DateIssued                                                        ║  ║  ║
║  ║  ║  SentToEmail                                                        ║  ║  ║
║  ║  ║  ReceiptStatus                                                     ║  ║  ║
║  ║  ║  Status                                                            ║  ║  ║
║  ║  ╚═════════════════════════════════════════════════════════════════╝  ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                                 🍿 FOOD_ORDERS                               ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Order_ID (PK)                                                            ║  ║
║  ║  Customer_ID (FK) ←───────────────────────────────────────────────────────╫──╫
║  ║  TimeSlot_ID (FK) ←───────────────────────────────────────────────────────╫──╫
║  ║  Item_ID                                                                  ║  ║
║  ║  Quantity                                                                 ║  ║
║  ║  UnitPrice                                                                ║  ║
║  ║  BookingRef                                                               ║  ║
║  ║  OrderTime                                                                ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                               ⏱️ CINEMA_QUEUE                               ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Queue_ID (PK)                                                            ║  ║
║  ║  mall_id (FK) ←──────────────────────────────────────────────────────────╫──╫
║  ║  area                                                                     ║  ║
║  ║  status                                                                   ║  ║
║  ║  queue_length                                                             ║  ║
║  ║  current_wait_mins                                                        ║  ║
║  ║  updated_at                                                               ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝

╔════════════════════════════════════════════════════════════════════════════════╗
║                              📬 REMINDERS_SENT                               ║
║  ╔═════════════════════════════════════════════════════════════════════════╗  ║
║  ║  Reminder_ID (PK)                                                         ║  ║
║  ║  Customer_ID (FK) ←───────────────────────────────────────────────────────╫──╫
║  ║  BookingRef                                                               ║  ║
║  ║  ReminderType                                                             ║  ║
║  ║  SentAt                                                                   ║  ║
║  ║  Status                                                                   ║  ║
║  ╚═════════════════════════════════════════════════════════════════════════╝  ║
╚════════════════════════════════════════════════════════════════════════════════╝
```

---

## 🔗 **SIMPLIFIED RELATIONSHIP MAP**

```
🏢 MALL
   │ (1:N)
   ▼
🎭 THEATER ──────────── 📅 TIMESLOT ──────────── 🎬 MOVIE
   │ (1:N)                │ (1:N)                │ (1:N)
   │                      │                      │
   ▼                      ▼                      ▼
💺 SEATS               🎟️ TICKET               (connected)
   │ (1:N)                │ (1:1)                │
   │                      │                      │
   └──────────────────────┘                      │
                          │                      │
                          ▼                      │
                       💳 PAYMENT ──────────────┘
                          │ (1:1)
                          ▼
                       🧾 E-RECEIPT

👤 CUSTOMER
   │ (1:N)
   ├─── 🎟️ TICKET
   ├─── 🔔 NOTIFICATIONS  
   ├─── 🍿 FOOD_ORDERS
   ├─── 📬 REMINDERS_SENT
   └─── 💳 PAYMENT

📅 TIMESLOT
   │ (1:N)
   ├─── 💺 SEATS
   ├─── 🎟️ TICKET
   └─── 🍿 FOOD_ORDERS
```

---

## 🎯 **KEY RELATIONSHIP TYPES**

### **📍 One-to-Many (1:N) - Most Common:**
- **mall → theater**: One mall has many theaters
- **theater → seats**: One theater has many seats  
- **theater → timeslot**: One theater has many showtimes
- **movie → timeslot**: One movie has many showtimes
- **timeslot → seats**: One showtime has many seats
- **timeslot → tickets**: One showtime has many tickets
- **customer → tickets**: One customer has many tickets
- **customer → notifications**: One customer has many notifications
- **customer → food_orders**: One customer has many food orders
- **customer → reminders**: One customer has many reminders
- **customer → payments**: One customer has many payments
- **timeslot → food_orders**: One showtime has many food orders
- **mall → cinema_queue**: One mall has many queue areas

### **📍 One-to-One (1:1) - Exact Matches:**
- **ticket ↔ payment**: Each ticket has exactly one payment
- **payment ↔ e-receipt**: Each payment has exactly one receipt

### **📍 Many-to-Many (N:M) - Through Junction Tables:**
- **movies ↔ theaters**: Through timeslots
- **customers ↔ movies**: Through tickets
- **customers ↔ showtimes**: Through tickets and food orders

---

## 💡 **HOW TO EXPLAIN TO PROFESSOR:**

### **Start with Physical Structure:**
> *"Ma'am, our database starts with the physical infrastructure: One **mall** contains many **theaters**, and each **theater** has many **seats**."*

### **Add Content Layer:**
> *"Then we add the content: One **movie** can have many **timeslots** (showtimes), and each **timeslot** happens in one specific **theater**."*

### **Bring in Customers:**
> *"When **customers** book tickets, they create **ticket** records that link them to specific **seats** for specific **timeslots**."*

### **Complete the Transaction:**
> *"Each **ticket** has exactly one **payment**, and each **payment** generates one **e-receipt** - creating a complete audit trail."*

### **Add Services:**
> *"We also track **notifications**, **food orders**, and **queue** information, all linked back to the relevant entities."*

This visual ERD with relationship lines makes it crystal clear how everything connects in your PeaksCinema database! 🎬✨
