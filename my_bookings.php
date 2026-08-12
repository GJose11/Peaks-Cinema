<?php
date_default_timezone_set('Asia/Manila');
session_start();
include("peakscinemas_database.php");
require_once(__DIR__ . "/seat_hold_helpers.php");
require_once(__DIR__ . "/cancellation_request_helpers.php");

ensureSeatHoldSchema($conn);
ensureCancellationRequestSchema($conn);
if(!isset($_SESSION['user_id'])){header("Location: index.php");exit;}
$uid=(int)$_SESSION['user_id'];
$weeklyCancelLimit = 3;
$weeklyCancelWindowDays = 7;

function fmt_dt($dt,$format='M d, Y g:i A'){
    if(empty($dt))return'';
    return date($format,strtotime($dt));
}
function fmt_time($t){
    if(empty($t))return'';
    $p=explode(':',trim($t));$h=(int)($p[0]??0);$m=str_pad((int)($p[1]??0),2,'0',STR_PAD_LEFT);
    return($h%12?:12).':'.$m.' '.($h>=12?'PM':'AM');
}

// ── Cancellation ─────────────────────────────────────────────────
$flashMsg='';$flashType='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['cancel_booking'])){
    $booking_ref=trim($_POST['booking_ref']??'');
    $timeslot_id=(int)($_POST['timeslot_id']??0);
    $cancel_reason=substr(trim($_POST['cancel_reason']??''),0,500);
    if(!$booking_ref||!$timeslot_id){$flashMsg="Invalid request.";$flashType='err';}
    elseif(strlen($cancel_reason)<8){$flashMsg="Please tell us why you want to cancel this booking.";$flashType='err';}
    elseif(getRecentCancellationRequestCount($conn,$uid,$weeklyCancelWindowDays) >= $weeklyCancelLimit){$flashMsg="You have reached the weekly cancellation limit. Please try again next week.";$flashType='err';}
    else{
        $pendingCheck=$conn->prepare("SELECT Request_ID FROM booking_cancellation_requests WHERE BookingRef=? AND Customer_ID=? AND Status='pending' LIMIT 1");
        $pendingCheck->bind_param("si",$booking_ref,$uid);$pendingCheck->execute();
        $pendingRequest=$pendingCheck->get_result()->fetch_assoc();
        if($pendingRequest){$flashMsg="A cancellation request for this booking is already waiting for admin review.";$flashType='err';}
        else{
        $ts_s=$conn->prepare("SELECT Date,StartTime FROM timeslot WHERE TimeSlot_ID=?");
        $ts_s->bind_param("i",$timeslot_id);$ts_s->execute();
        $ts=$ts_s->get_result()->fetch_assoc();
        if(!$ts){$flashMsg="Screening not found.";$flashType='err';}
        else{
            // v3.1 - Fix premature cancellation closure for afternoon screenings
            $screeningTimestamp = strtotime($ts['Date'] . ' ' . $ts['StartTime']);
            $currentTimestamp = time();
            $minsLeft = ($screeningTimestamp - $currentTimestamp) / 60;

            if ($minsLeft < 120) {
                $flashMsg = "Cancellation only allowed up to 2 hours before the screening.";
                $flashType = 'err';
            } else {
                $chk=$conn->prepare("SELECT Ticket_ID FROM ticket WHERE BookingRef=? AND Customer_ID=? AND Status=1");
                $chk->bind_param("si",$booking_ref,$uid);$chk->execute();
                $tickets=$chk->get_result()->fetch_all(MYSQLI_ASSOC);
                if(empty($tickets)){$flashMsg="No active tickets found.";$flashType='err';}
                else{
                    $conn->begin_transaction();
                    try{
                        $req=$conn->prepare("INSERT INTO booking_cancellation_requests(BookingRef,Customer_ID,TimeSlot_ID,Reason) VALUES(?,?,?,?)");
                        $req->bind_param("siis",$booking_ref,$uid,$timeslot_id,$cancel_reason);
                        $req->execute();
                        $title="Booking Cancelled";$msg="Your booking (Ref: $booking_ref) has been cancelled. Refund within 3–5 business days.";$type='cancellation';
                        $title="Cancellation Request Sent";$msg="Your cancellation/refund request for booking $booking_ref was sent for admin review. We will notify you once it has been checked.";
                        addCustomerNotification($conn,$uid,$title,$msg,'cancellation');
                        $conn->commit();$flashMsg="Cancellation request for booking $booking_ref submitted. Please wait for admin approval.";$flashType='ok';
                    }catch(Throwable $e){$conn->rollback();$flashMsg="Error sending cancellation request. Please try again.";$flashType='err';}
                }
            }
        }
    }
}

// ── Profile ───────────────────────────────────────────────────────
}
$weeklyCancelCount = getRecentCancellationRequestCount($conn,$uid,$weeklyCancelWindowDays);
$weeklyCancelRemaining = max(0,$weeklyCancelLimit - $weeklyCancelCount);

$ps=$conn->prepare("SELECT Name,ProfilePhoto FROM customer WHERE Customer_ID=?");
$ps->bind_param("i",$uid);$ps->execute();$user=$ps->get_result()->fetch_assoc();
$profile_photo=$user['ProfilePhoto']??$_SESSION['profile_photo']??null;
$np=explode(' ',trim($user['Name']??''));
$user_initials=strtoupper(substr($np[0]??'',0,1).substr(end($np)??'',0,1));
if(strlen($user_initials)===1)$user_initials=strtoupper(substr($np[0]??'',0,2));

// ── Active bookings ───────────────────────────────────────────────
$as=$conn->prepare("SELECT tk.Ticket_ID,COALESCE(tk.BookingRef,CONCAT('TK-',tk.Ticket_ID)) AS BookingRef,tk.Price,tk.DateTime,tk.Status,tk.TimeSlot_ID,m.MovieName,m.MoviePoster,ml.MallName,th.TheaterName,ts.Date,ts.StartTime,ts.ScreeningType,s.SeatRow,s.SeatColumn,s.SeatType FROM ticket tk LEFT JOIN movie m ON m.Movie_ID=tk.Movie_ID LEFT JOIN timeslot ts ON ts.TimeSlot_ID=tk.TimeSlot_ID LEFT JOIN theater th ON th.Theater_ID=ts.Theater_ID LEFT JOIN mall ml ON ml.Mall_ID=th.Mall_ID LEFT JOIN seats s ON s.Seat_ID=tk.Seat_ID WHERE tk.Customer_ID=? AND tk.Status=1 ORDER BY tk.DateTime DESC");
$as->bind_param("i",$uid);$as->execute();
$active_rows=$as->get_result()->fetch_all(MYSQLI_ASSOC);
$active_bookings=[];$now=time();
foreach($active_rows as $row){
    $ref=$row['BookingRef'];
    $st=strtotime($row['Date'].' '.$row['StartTime']);
    $already_passed = ($st <= $now);
    if($already_passed)continue;
    if(!isset($active_bookings[$ref])){
        $active_bookings[$ref]=['ref'=>$ref,'movie'=>$row['MovieName'],'poster'=>$row['MoviePoster'],'mall'=>$row['MallName'],'theater'=>$row['TheaterName'],'date'=>$row['Date'],'start_time'=>$row['StartTime'],'type'=>$row['ScreeningType'],'timeslot_id'=>$row['TimeSlot_ID'],'booked_at'=>$row['DateTime'],'screening_time'=>$st,'already_passed'=>$already_passed,'total'=>0,'seats'=>[]];
    }
    if(!empty($row['SeatRow'])&&(isset($row['SeatColumn']))){
        $d=(int)$row['SeatColumn']+1;
        $active_bookings[$ref]['seats'][]=$row['SeatRow'].$d.(!empty($row['SeatType'])?' ('.$row['SeatType'].')':'');
    }
    $active_bookings[$ref]['total']+=$row['Price'];
}
uasort($active_bookings,fn($a,$b)=>strtotime($b['booked_at'])-strtotime($a['booked_at']));

// ── Past bookings ─────────────────────────────────────────────────
$pendingCancellationRequests = getPendingCancellationRequestsByBooking($conn, $uid);
$hasCa=$conn->query("SHOW COLUMNS FROM ticket LIKE 'CancelledAt'")->num_rows>0;
$cc=$hasCa?'tk.CancelledAt':'NULL';
$ps2=$conn->prepare("SELECT tk.Ticket_ID,COALESCE(tk.BookingRef,CONCAT('TK-',tk.Ticket_ID)) AS BookingRef,tk.Price,tk.DateTime,tk.Status,$cc AS CancelledAt,m.MovieName,m.MoviePoster,ml.MallName,th.TheaterName,ts.Date,ts.StartTime,ts.ScreeningType,s.SeatRow,s.SeatColumn,s.SeatType FROM ticket tk JOIN movie m ON m.Movie_ID=tk.Movie_ID JOIN timeslot ts ON ts.TimeSlot_ID=tk.TimeSlot_ID JOIN theater th ON th.Theater_ID=ts.Theater_ID JOIN mall ml ON ml.Mall_ID=th.Mall_ID LEFT JOIN seats s ON s.Seat_ID=tk.Seat_ID WHERE tk.Customer_ID=? AND tk.Status IN(2,3) ORDER BY tk.DateTime DESC LIMIT 50");
$ps2->bind_param("i",$uid);$ps2->execute();
$past_rows=$ps2->get_result()->fetch_all(MYSQLI_ASSOC);
$past_bookings=[];
foreach($past_rows as $row){
    $ref=$row['BookingRef'];
    if(!isset($past_bookings[$ref])){$past_bookings[$ref]=['ref'=>$ref,'movie'=>$row['MovieName'],'poster'=>$row['MoviePoster'],'mall'=>$row['MallName'],'theater'=>$row['TheaterName'],'date'=>$row['Date'],'start_time'=>$row['StartTime'],'type'=>$row['ScreeningType'],'booked_at'=>$row['DateTime'],'status'=>$row['Status'],'cancelled_at'=>$row['CancelledAt'],'total'=>0,'seats'=>[]];}
    if(!empty($row['SeatRow'])&&(isset($row['SeatColumn']))){$d=(int)$row['SeatColumn']+1;$past_bookings[$ref]['seats'][]=$row['SeatRow'].$d;}
    $past_bookings[$ref]['total']+=$row['Price'];
}
uasort($past_bookings,fn($a,$b)=>strtotime($b['booked_at'])-strtotime($a['booked_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
<title>My Bookings – Peak's Cinema</title>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Outfit',sans-serif;background:#0f0f0f;color:#F9F9F9;min-height:100vh;padding-top:80px;padding-bottom:80px;}
body::before{content:'';position:fixed;inset:0;background:url('movie-background-collage.jpg') center/cover no-repeat;opacity:0.09;z-index:0;pointer-events:none;}
body::after{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at center,transparent 10%,rgba(15,15,15,0.6) 60%,#0f0f0f 100%);z-index:1;pointer-events:none;}

/* Header */
header{background:#161616;display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:fixed;top:0;left:0;width:100%;height:60px;z-index:1000;border-bottom:1px solid rgba(255,255,255,0.07);transition:transform 0.35s cubic-bezier(0.4,0,0.2,1);}
.logo,.brand-logo-wrap{display:inline-flex;align-items:center;text-decoration:none;transition:transform 0.2s cubic-bezier(0.4,0,0.2,1);cursor:pointer;flex-shrink:0;}
.brand-logo-wrap:hover{transform:scale(1.05);}
.brand-logo{height:34px;width:auto;filter:invert(1);display:block;}
.header-actions{display:flex;align-items:center;gap:10px;}
.bookings-btn,.notif-btn{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:7px;padding:6px 9px;color:rgba(249,249,249,0.65);font-size:0.92rem;cursor:pointer;transition:all 0.25s;font-family:'Outfit',sans-serif;display:flex;align-items:center;height:32px;}
.bookings-btn:hover,.notif-btn:hover{background:rgba(255,255,255,0.12);color:#F9F9F9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.bookings-btn.here,.bookings-btn.active{background:rgba(255,77,77,0.12);border-color:rgba(255,77,77,0.4);color:#ff4d4d;}
.bookings-btn::after{content:'My Bookings';font-size:0.75rem;font-weight:600;}
.notif-btn.here,.notif-btn.active{background:rgba(255,77,77,0.12);border-color:rgba(255,77,77,0.4);color:#ff4d4d;}
.notif-btn{position:relative;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:7px;padding:6px 9px;color:rgba(249,249,249,0.65);font-size:0.92rem;cursor:pointer;transition:all 0.25s;font-family:'Outfit',sans-serif;display:flex;align-items:center;height:32px;}
.notif-btn:hover{background:rgba(255,255,255,0.12);color:#F9F9F9;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.2);}
.notif-badge{position:absolute;top:-4px;right:-4px;background:#ff4d4d;color:#fff;font-size:0.55rem;font-weight:800;min-width:16px;height:16px;border-radius:8px;padding:0 4px;display:none;align-items:center;justify-content:center;}
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;background:#1a1a1a;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.6);z-index:2000;}
.notif-dropdown.open{display:block;}
.notif-header{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;}
.notif-header span{font-size:0.78rem;font-weight:700;color:rgba(249,249,249,0.5);letter-spacing:1px;text-transform:uppercase;}
.notif-mark-all{font-size:0.7rem;color:#ff6b6b;cursor:pointer;background:none;border:none;font-family:'Outfit',sans-serif;font-weight:600;}
.notif-list{max-height:300px;overflow-y:auto;}
.notif-item{padding:12px 16px 12px 10px;border-bottom:1px solid rgba(255,255,255,0.04);cursor:pointer;transition:background 0.15s;display:flex;gap:6px;align-items:flex-start;text-decoration:none;color:inherit;}
.notif-item:hover{background:rgba(255,255,255,0.04);}
.notif-item.unread{background:rgba(255,77,77,0.05);}
.notif-dot{width:6px;height:6px;border-radius:50%;background:#ff4d4d;flex-shrink:0;margin-top:6px;}
.notif-dot.read{background:transparent;}
.notif-item-body{flex:1;min-width:0;}
.notif-item-title{font-size:0.8rem;font-weight:700;margin-bottom:2px;color:#F9F9F9;}
.notif-item-msg{font-size:0.72rem;color:rgba(249,249,249,0.4);line-height:1.5;}
.notif-item-time{font-size:0.65rem;color:rgba(249,249,249,0.25);margin-top:4px;}
.notif-empty{text-align:center;padding:28px;font-size:0.8rem;color:rgba(249,249,249,0.2);}
.notif-footer-dd{padding:10px 16px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;}
.notif-footer-dd a{font-size:0.75rem;color:#ff6b6b;text-decoration:none;font-weight:600;}
.notif-footer-dd a.dim{color:rgba(249,249,249,0.3);}
.profile-btn{background:#F9F9F9;border:none;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;padding:0;transition:transform 0.2s,box-shadow 0.2s;flex-shrink:0;}
.profile-btn img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.profile-btn:hover{transform:scale(1.08);box-shadow:0 0 14px rgba(255,255,255,0.25);}
.profile-initials{width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,#ff4d4d,#c0392b);display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:800;color:#fff;}

/* Page */
.page{position:relative;z-index:10;width:92%;max-width:920px;margin:0 auto;}
.page-eyebrow{font-size:0.65rem;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;color:#ff4d4d;margin-bottom:6px;}
.page-heading{font-size:1.75rem;font-weight:900;letter-spacing:-0.5px;margin-bottom:6px;}
.page-sub{font-size:0.82rem;color:rgba(249,249,249,0.38);margin-bottom:28px;}

/* Tabs — same as notifications */
.tabs-row{display:flex;gap:6px;margin-bottom:24px;background:#161616;border:1px solid rgba(255,255,255,0.07);border-radius:12px;padding:5px;width:fit-content;}
.tab{padding:8px 22px;border-radius:9px;border:none;font-family:'Outfit',sans-serif;font-size:0.82rem;font-weight:700;color:rgba(249,249,249,0.45);background:none;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:7px;white-space:nowrap;}
.tab.active{background:#ff4d4d;color:#fff;box-shadow:0 3px 12px rgba(255,77,77,0.3);}
.tab:hover:not(.active){background:rgba(255,255,255,0.06);color:#F9F9F9;}
.tab-count{background:rgba(255,255,255,0.18);color:#fff;font-size:0.62rem;font-weight:800;padding:1px 7px;border-radius:10px;line-height:1.6;}
.tab:not(.active) .tab-count{background:rgba(255,255,255,0.07);color:rgba(249,249,249,0.45);}

/* Flash messages */
.flash{padding:13px 18px;border-radius:12px;font-size:0.82rem;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.flash.ok{background:rgba(102,187,106,0.08);border:1px solid rgba(102,187,106,0.2);color:#81c784;}
.flash.err{background:rgba(255,77,77,0.07);border:1px solid rgba(255,77,77,0.2);border-left:3px solid #ff4d4d;color:#ff6b6b;}

/* Policy note */
.policy-note{font-size:0.75rem;color:rgba(249,249,249,0.32);margin-bottom:20px;padding:11px 16px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid rgba(255,255,255,0.06);line-height:1.65;}

/* ── Booking card — shared surface with notifications ── */
.booking-card{background:#161616;border:1px solid rgba(255,255,255,0.07);border-radius:16px;overflow:hidden;margin-bottom:12px;box-shadow:0 2px 12px rgba(0,0,0,0.3);transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;position:relative;}
.booking-card:hover{border-color:rgba(255,255,255,0.14);box-shadow:0 8px 28px rgba(0,0,0,0.5);background:#1a1a1a;}
.booking-card .accent-bar{position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(to bottom,#ff4d4d,#c0392b);}

/* Card top */
.bc-top{display:flex;gap:16px;padding:18px 20px 16px 22px;border-bottom:1px solid rgba(255,255,255,0.06);}
.bc-poster{width:56px;height:84px;flex-shrink:0;border-radius:10px;background-size:cover;background-position:center;background-color:#111;border:1px solid rgba(255,255,255,0.08);box-shadow:0 4px 12px rgba(0,0,0,0.4);}
.bc-info{flex:1;min-width:0;}
.bc-movie{font-size:1rem;font-weight:800;color:#F9F9F9;margin-bottom:7px;line-height:1.2;}
.bc-meta{display:flex;flex-direction:column;gap:4px;}
.bc-meta-row{font-size:0.75rem;color:rgba(249,249,249,0.45);display:flex;align-items:center;gap:5px;}
.bc-meta-row strong{color:rgba(249,249,249,0.7);font-weight:600;}
.bc-badges{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.bc-ref{font-size:0.62rem;font-weight:800;letter-spacing:1px;background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.22);color:#ff6b6b;padding:3px 10px;border-radius:20px;}
.status-badge{padding:3px 10px;border-radius:20px;font-size:0.65rem;font-weight:700;letter-spacing:0.5px;}
.badge-confirmed{background:rgba(102,187,106,0.12);border:1px solid rgba(102,187,106,0.25);color:#81c784;}
.badge-pending{background:rgba(255,213,79,0.12);border:1px solid rgba(255,213,79,0.24);color:#FFD54F;}
.badge-cancelled{background:rgba(255,77,77,0.1);border:1px solid rgba(255,77,77,0.2);color:#ff6b6b;}
.badge-completed{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:rgba(249,249,249,0.45);}
.bc-badges.has-pending .badge-confirmed{display:none;}

/* Card bottom */
.bc-bottom{display:flex;align-items:center;justify-content:space-between;padding:12px 20px 12px 22px;gap:14px;flex-wrap:wrap;}
.bc-seats{display:flex;flex-wrap:wrap;gap:5px;}
.seat-chip{padding:3px 10px;border-radius:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);font-size:0.7rem;font-weight:700;color:rgba(249,249,249,0.6);}
.bc-right{display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;}
.bc-total{font-size:1.05rem;font-weight:900;color:#ff4d4d;white-space:nowrap;}
.btn-cancel{padding:7px 18px;border-radius:8px;border:1px solid rgba(255,77,77,0.3);background:rgba(255,77,77,0.08);color:#ff6b6b;font-family:'Outfit',sans-serif;font-size:0.75rem;font-weight:700;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:5px;}
.btn-cancel:hover{background:#ff4d4d;color:#fff;border-color:#ff4d4d;}
.cancel-closed{font-size:0.7rem;color:rgba(255,107,107,0.6);font-weight:600;}
.cancel-pending{font-size:0.7rem;color:#FFD54F;font-weight:700;}
.cancel-limit{font-size:0.7rem;color:rgba(255,213,79,0.85);font-weight:700;}

/* Empty */
.empty-state{text-align:center;padding:60px 20px;color:rgba(249,249,249,0.25);}
.empty-icon{font-size:3rem;margin-bottom:14px;opacity:0.7;}
.empty-title{font-size:1rem;font-weight:800;color:rgba(249,249,249,0.4);margin-bottom:6px;}
.empty-sub{font-size:0.8rem;margin-bottom:20px;}
.empty-link{display:inline-block;padding:9px 24px;border-radius:9px;background:#ff4d4d;color:#fff;font-weight:700;font-size:0.82rem;text-decoration:none;transition:all 0.2s;}
.empty-link:hover{background:#e03c3c;transform:translateY(-1px);}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:3000;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.active{display:flex;}
.modal-box{background:#161616;border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:30px 26px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.7);display:flex;flex-direction:column;}
.modal-title{order:0;font-size:1.1rem;font-weight:900;margin-bottom:10px;}
.modal-sub{order:1;font-size:0.82rem;color:rgba(249,249,249,0.45);margin-bottom:22px;line-height:1.65;}
.modal-label{order:2;display:block;font-size:0.72rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(249,249,249,0.45);margin-bottom:8px;}
.modal-textarea{order:3;width:100%;min-height:110px;border-radius:12px;border:1px solid rgba(255,255,255,0.12);background:#111;color:#F9F9F9;padding:12px 14px;font-family:'Outfit',sans-serif;font-size:0.84rem;resize:vertical;outline:none;margin-bottom:10px;}
.modal-textarea:focus{border-color:rgba(255,77,77,0.45);}
.modal-note{order:4;font-size:0.72rem;color:rgba(249,249,249,0.34);margin-bottom:18px;line-height:1.5;}
.modal-btns{order:5;display:flex;gap:10px;}
.btn-confirm{flex:1;padding:12px;border-radius:10px;border:none;background:#ff4d4d;color:#fff;font-family:'Outfit',sans-serif;font-size:0.88rem;font-weight:800;cursor:pointer;transition:background 0.2s;box-shadow:0 4px 14px rgba(255,77,77,0.3);}
.btn-confirm:hover{background:#e03c3c;}
.btn-dismiss{flex:1;padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:rgba(249,249,249,0.55);font-family:'Outfit',sans-serif;font-size:0.88rem;font-weight:600;cursor:pointer;transition:all 0.2s;}
.btn-dismiss:hover{background:rgba(255,255,255,0.09);color:#F9F9F9;}

#cancelForm{margin:0;}

@media(max-width:640px){
    .page{width:95%;}
    .page-heading{font-size:1.45rem;}
    .bc-top{padding:14px 16px 12px 18px;}
    .bc-bottom{padding:10px 16px 10px 18px;}
    .bc-poster{width:48px;height:72px;}
    .bc-movie{font-size:0.92rem;}
    .tabs-row{width:100%;}
    .tab{flex:1;justify-content:center;padding:8px 10px;font-size:0.78rem;}
}
</style>
</head>
<body>

<header id="mainHeader">
    <a href="home.php" class="brand-logo-wrap" title="Peak's Cinema - Home">
        <img src="peakscinematransparent.png" alt="Peak's Cinema Logo" class="brand-logo">
    </a>
    <div class="header-actions">
        <button type="button" class="bookings-btn here" onclick="window.location.href='my_bookings.php'" title="My Bookings">
            🎟
        </button>
        <div class="notif-wrap">
            <button class="notif-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifications">
                🔔<span class="notif-badge" id="notifBadge"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header"><span>Notifications</span><button class="notif-mark-all" onclick="markAllRead()">Mark all read</button></div>
                <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
                <div class="notif-footer-dd">
                    <a href="notifications_page.php">View All →</a>
                    <a href="my_bookings.php" class="dim">My Bookings</a>
                </div>
            </div>
        </div>
        <button class="profile-btn" onclick="window.location.href='profile_dashboard.php'" title="Profile Dashboard">
            <?php if(!empty($profile_photo)): ?><img src="<?=htmlspecialchars($profile_photo)?>" referrerpolicy="no-referrer" alt="Profile">
            <?php elseif(!empty($user_initials)): ?><div class="profile-initials"><?=htmlspecialchars($user_initials)?></div>
            <?php else: ?><div class="profile-initials">?</div><?php endif; ?>
        </button>
    </div>
</header>

<form id="cancelForm" method="POST">
    <input type="hidden" name="cancel_booking" value="1">
    <input type="hidden" name="booking_ref" id="cancelRef">
    <input type="hidden" name="timeslot_id" id="cancelTimeslot">
    <input type="hidden" name="cancel_reason" id="cancelReasonInput">
</form>

<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <label class="modal-label" for="cancelReason">Why do you want to cancel?</label>
        <textarea id="cancelReason" class="modal-textarea" maxlength="500" placeholder="Please share your reason for cancellation." required></textarea>
        <div class="modal-note">You can submit up to <?= $weeklyCancelLimit ?> cancellation requests every <?= $weeklyCancelWindowDays ?> days. Remaining this week: <?= $weeklyCancelRemaining ?>.</div>
        <h2 class="modal-title">🗑 Cancel Booking?</h2>
        <p class="modal-sub" id="modalSub">This will cancel your booking and free the seats. Refund will be processed within 3–5 business days.</p>
        <div class="modal-btns">
            <button type="button" class="btn-confirm" onclick="submitCancelRequest()">Send Request</button>
            <button type="button" class="btn-dismiss" onclick="closeCancelModal()">Keep Booking</button>
        </div>
    </div>
</div>

<div class="page">
    <div class="page-eyebrow">My Account</div>
    <h1 class="page-heading">My Bookings</h1>
    <p class="page-sub"><?=count($active_bookings)?> active · <?=count($past_bookings)?> past</p>

    <?php if(!empty($flashMsg)): ?>
    <div class="flash <?=$flashType?>"><?=$flashType==='ok'?'✓':'⚠'?> <?=htmlspecialchars($flashMsg)?></div>
    <?php endif; ?>

    <div class="tabs-row">
        <button class="tab active" id="tabActive" onclick="switchTab('active')">
            🎬 Active <?php if(count($active_bookings)): ?><span class="tab-count"><?=count($active_bookings)?></span><?php endif; ?>
        </button>
        <button class="tab" id="tabPast" onclick="switchTab('past')">
            📋 Past <?php if(count($past_bookings)): ?><span class="tab-count"><?=count($past_bookings)?></span><?php endif; ?>
        </button>
    </div>

    <!-- Active bookings -->
    <div id="sectionActive">
        <div class="policy-note">Requests are reviewed by admin before tickets are cancelled and refunds are processed. Limit: <strong><?= $weeklyCancelLimit ?></strong> requests every <strong><?= $weeklyCancelWindowDays ?></strong> days. Remaining this week: <strong><?= $weeklyCancelRemaining ?></strong>.</div>
        <div class="policy-note">📋 <strong>Cancellation Policy:</strong> Cancel at least <strong>2 hours before</strong> showtime. Refunds processed within 3–5 business days.</div>

        <?php if(empty($active_bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">🎟</div>
            <div class="empty-title">No active bookings</div>
            <p class="empty-sub">Your upcoming tickets will appear here.</p>
            <a href="home.php" class="empty-link">Browse Movies →</a>
        </div>
        <?php endif; ?>

        <?php foreach ($active_bookings as $b):
            $screeningTimestamp = strtotime($b['date'] . ' ' . $b['start_time']);
            $currentTimestamp = time();
            $already_passed = ($screeningTimestamp <= $currentTimestamp);

            if ($already_passed) continue;

            $minsLeft = ($screeningTimestamp - $currentTimestamp) / 60;
            $canCancel = ($minsLeft >= 120);
            $pendingRequest = $pendingCancellationRequests[$b['ref']] ?? null;
            $hasPendingRequest = !empty($pendingRequest); ?>
        <div class="booking-card">
            <div class="accent-bar"></div>
            <div class="bc-top">
                <div class="bc-poster" style="background-image:url('<?=htmlspecialchars($b['poster'])?>');"></div>
                <div class="bc-info">
                    <div class="bc-movie"><?=htmlspecialchars($b['movie'])?></div>
                    <div class="bc-meta">
                        <div class="bc-meta-row">📍 <strong><?=htmlspecialchars($b['mall'])?></strong> · <?=htmlspecialchars($b['theater'])?></div>
                        <div class="bc-meta-row">📅 <strong><?=date('F d, Y',strtotime($b['date']))?></strong> · <?=fmt_time($b['start_time'])?></div>
                        <div class="bc-meta-row">🎞 <?=htmlspecialchars($b['type'])?></div>
                        <div class="bc-meta-row">🕒 Booked <?=fmt_dt($b['booked_at'],'M d, Y g:i A')?></div>
                        <?php if($hasPendingRequest): ?>
                        <div class="bc-meta-row">Pending request since <?=fmt_dt($pendingRequest['RequestedAt'],'M d, Y g:i A')?></div>
                        <?php endif; ?>
                    </div>
                    <div class="bc-badges<?=$hasPendingRequest ? ' has-pending' : ''?>">
                        <span class="bc-ref"><?=htmlspecialchars($b['ref'])?></span>
                        <?php if($hasPendingRequest): ?><span class="status-badge badge-pending">Pending Review</span><?php endif; ?>
                        <span class="status-badge badge-confirmed">✓ Confirmed</span>
                    </div>
                </div>
            </div>
            <div class="bc-bottom">
                <div class="bc-seats">
                    <?php foreach($b['seats'] as $s): ?>
                    <span class="seat-chip"><?=htmlspecialchars($s)?></span>
                    <?php endforeach; ?>
                </div>
                <div class="bc-right">
                    <span class="bc-total">₱<?=number_format($b['total'],2)?></span>
                    <?php if($canCancel && $weeklyCancelRemaining > 0 && !$hasPendingRequest): ?>
                    <button class="btn-cancel" onclick="openCancel('<?=htmlspecialchars($b['ref'])?>',<?=(int)$b['timeslot_id']?>,'<?=htmlspecialchars($b['movie'])?>')">🗑 Cancel</button>
                    <?php else: ?>
                    <?php if($hasPendingRequest): ?><span class="cancel-pending">Pending admin review</span><?php elseif($canCancel): ?><span class="cancel-limit">Weekly cancellation limit reached</span><?php else: ?>
                    <span class="cancel-closed">⏰ Cancellation closed</span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Past bookings -->
    <div id="sectionPast" style="display:none;">
        <?php if(empty($past_bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <div class="empty-title">No past bookings</div>
            <p class="empty-sub">Your booking history will show here.</p>
        </div>
        <?php else: foreach($past_bookings as $b): $cancelled=($b['status']==2); ?>
        <div class="booking-card" style="opacity:<?=$cancelled?'0.6':'0.85'?>;">
            <div class="accent-bar" style="background:<?=$cancelled?'linear-gradient(to bottom,#ff4d4d,#c0392b)':'linear-gradient(to bottom,rgba(255,255,255,0.3),rgba(255,255,255,0.1))'?>;"></div>
            <div class="bc-top">
                <div class="bc-poster" style="background-image:url('<?=htmlspecialchars($b['poster'])?>');"></div>
                <div class="bc-info">
                    <div class="bc-movie"><?=htmlspecialchars($b['movie'])?></div>
                    <div class="bc-meta">
                        <div class="bc-meta-row">📍 <strong><?=htmlspecialchars($b['mall'])?></strong> · <?=htmlspecialchars($b['theater'])?></div>
                        <div class="bc-meta-row">📅 <?=date('F d, Y',strtotime($b['date']))?> · <?=fmt_time($b['start_time'])?></div>
                        <div class="bc-meta-row">🕒 Booked <?=fmt_dt($b['booked_at'],'M d, Y g:i A')?></div>
                        <?php if($cancelled&&!empty($b['cancelled_at'])): ?>
                        <div class="bc-meta-row">❌ Cancelled <?=fmt_dt($b['cancelled_at'],'M d, Y g:i A')?></div>
                        <?php endif; ?>
                    </div>
                    <div class="bc-badges">
                        <span class="bc-ref"><?=htmlspecialchars($b['ref'])?></span>
                        <span class="status-badge <?=$cancelled?'badge-cancelled':'badge-completed'?>"><?=$cancelled?'Cancelled':'Completed'?></span>
                    </div>
                </div>
            </div>
            <div class="bc-bottom">
                <div class="bc-seats">
                    <?php foreach($b['seats'] as $s): ?>
                    <span class="seat-chip"><?=htmlspecialchars($s)?></span>
                    <?php endforeach; ?>
                </div>
                <span class="bc-total">₱<?=number_format($b['total'],2)?></span>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<script>
function switchTab(t){
    document.getElementById('sectionActive').style.display=t==='active'?'':'none';
    document.getElementById('sectionPast').style.display=t==='past'?'':'none';
    document.getElementById('tabActive').classList.toggle('active',t==='active');
    document.getElementById('tabPast').classList.toggle('active',t==='past');
}
function openCancel(ref,tid,movie){
    document.getElementById('cancelRef').value=ref;
    document.getElementById('cancelTimeslot').value=tid;
    document.getElementById('cancelReason').value='';
    document.getElementById('modalSub').textContent=`Tell us why you want to cancel booking "${ref}" for ${movie}. The admin team will review your request first.`;
    document.getElementById('cancelModal').classList.add('active');
}
function closeCancelModal(){
    document.getElementById('cancelModal').classList.remove('active');
}
function submitCancelRequest(){
    const reason = document.getElementById('cancelReason').value.trim();
    if(reason.length < 8){
        alert('Please enter a short reason for your cancellation request.');
        return;
    }
    document.getElementById('cancelReasonInput').value = reason;
    document.getElementById('cancelForm').submit();
}
document.getElementById('cancelModal').addEventListener('click',function(e){if(e.target===this)closeCancelModal();});

// Notifications dropdown
function toggleNotif(e){e.stopPropagation();const dd=document.getElementById('notifDropdown');dd.classList.toggle('open');if(dd.classList.contains('open'))loadNotif();}
document.addEventListener('click',()=>document.getElementById('notifDropdown')?.classList.remove('open'));
function loadNotif(){
    fetch('notifications_api.php').then(r=>r.json()).then(data=>{
        const list=document.getElementById('notifList'),badge=document.getElementById('notifBadge');
        if(data.unread>0){badge.textContent=data.unread>9?'9+':data.unread;badge.style.display='flex';}else badge.style.display='none';
        if(!data.notifications?.length){list.innerHTML='<div class="notif-empty">No notifications yet.</div>';return;}
        list.innerHTML=data.notifications.map(n=>`<a class="notif-item ${n.IsRead==0?'unread':''}" href="notifications_page.php?id=${n.Notif_ID}" style="text-decoration:none;color:inherit;"><div class="notif-dot ${n.IsRead==1?'read':''}"></div><div class="notif-item-body"><div class="notif-item-title">${n.Title}</div><div class="notif-item-msg">${n.Message}</div><div class="notif-item-time">${n.time_ago}</div></div></a>`).join('');
    }).catch(()=>{});
}
function markAllRead(){fetch('notifications_api.php?action=mark_read').then(()=>loadNotif()).catch(()=>{});}
loadNotif();setInterval(loadNotif,60000);

(function(){
    const h=document.getElementById('mainHeader');let last=window.scrollY,tick=false;
    window.addEventListener('scroll',()=>{if(!tick){requestAnimationFrame(()=>{const cur=window.scrollY;h.style.transform=(cur>last&&cur>80)?'translateY(-100%)':'translateY(0)';last=cur;tick=false;});tick=true;}},{passive:true});
})();
</script>
</body>
</html>
