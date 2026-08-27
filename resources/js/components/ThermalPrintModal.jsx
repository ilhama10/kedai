import React, { useState } from 'react';
import axios from 'axios';
import { Printer, RefreshCw } from 'lucide-react';

export default function ThermalPrintModal({ order, onClose }) {
    const [paperWidth, setPaperWidth] = useState('58mm');
    const [printing, setPrinting] = useState(false);

    const handlePrint = () => {
        setPrinting(true);
        axios.post(`/api/orders/${order.id}/reprint-receipt`, { paper_width: paperWidth })
            .then(res => {
                setPrinting(false);
                window.print();
            })
            .catch(err => {
                setPrinting(false);
                alert(err.response?.data?.message || 'Gagal membuat job pencetakan nota.');
            });
    };

    return (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white w-full max-w-sm rounded-3xl p-5 shadow-2xl flex flex-col max-h-[90vh]">
                <div className="flex justify-between items-center mb-3">
                    <h3 className="font-bold text-base text-gray-900">Cetak Nota Thermal</h3>
                    <button onClick={onClose} className="text-gray-400 font-bold p-1">✕</button>
                </div>

                <div className="flex gap-2 mb-3">
                    <button
                        onClick={() => setPaperWidth('58mm')}
                        className={`flex-1 py-1.5 rounded-xl text-xs font-bold border ${paperWidth === '58mm' ? 'bg-brand-600 text-white' : 'bg-gray-50 text-gray-700'}`}
                    >
                        Thermal 58mm
                    </button>
                    <button
                        onClick={() => setPaperWidth('80mm')}
                        className={`flex-1 py-1.5 rounded-xl text-xs font-bold border ${paperWidth === '80mm' ? 'bg-brand-600 text-white' : 'bg-gray-50 text-gray-700'}`}
                    >
                        Thermal 80mm
                    </button>
                </div>

                {/* ESC/POS Thermal Receipt Layout Preview */}
                <div className="flex-1 overflow-y-auto bg-amber-50/50 p-4 border border-amber-200 font-mono text-xs text-gray-900 rounded-xl space-y-2" id="thermal-receipt">
                    <div className="text-center font-bold">
                        <p className="text-sm uppercase tracking-wider">KEDAI LESEHAN</p>
                        <p className="text-[10px]">{order.branch?.name}</p>
                        <p className="text-[9px] text-gray-600">{order.branch?.address}</p>
                        <p className="text-[9px] text-gray-600">Telp: {order.branch?.phone}</p>
                    </div>

                    <div className="border-b border-dashed border-gray-400 my-1"></div>

                    <div className="space-y-0.5 text-[11px]">
                        <p>No: {order.order_number}</p>
                        <p>Tgl: {new Date(order.created_at).toLocaleString('id-ID')}</p>
                        <p>Cust: {order.customer_name}</p>
                        <p>Tipe: {order.order_type} {order.table ? `(${order.table.table_number})` : ''}</p>
                    </div>

                    <div className="border-b border-dashed border-gray-400 my-1"></div>

                    <div className="space-y-1 text-[11px]">
                        {order.order_items?.map((item, idx) => (
                            <div key={idx}>
                                <div className="flex justify-between">
                                    <span>{item.quantity}x {item.menu_name_snapshot}</span>
                                    <span>{item.total_price.toLocaleString('id-ID')}</span>
                                </div>
                                {item.variants?.map((v, vi) => (
                                    <p key={vi} className="text-[9px] text-gray-600 pl-3">+ {v.name_snapshot}</p>
                                ))}
                                {item.addons?.map((a, ai) => (
                                    <p key={ai} className="text-[9px] text-gray-600 pl-3">+ {a.name_snapshot}</p>
                                ))}
                            </div>
                        ))}
                    </div>

                    <div className="border-b border-dashed border-gray-400 my-1"></div>

                    <div className="space-y-0.5 text-[11px] text-right font-semibold">
                        <div className="flex justify-between"><span>Subtotal:</span><span>Rp {order.subtotal?.toLocaleString('id-ID')}</span></div>
                        {order.discount_amount > 0 && (
                            <div className="flex justify-between text-red-600"><span>Diskon:</span><span>-Rp {order.discount_amount?.toLocaleString('id-ID')}</span></div>
                        )}
                        <div className="flex justify-between"><span>Pajak (10% PB1):</span><span>Rp {order.tax_amount?.toLocaleString('id-ID')}</span></div>
                        <div className="flex justify-between font-bold text-xs pt-1 border-t border-dashed">
                            <span>TOTAL:</span>
                            <span>Rp {order.total_amount?.toLocaleString('id-ID')}</span>
                        </div>
                    </div>

                    <div className="border-b border-dashed border-gray-400 my-1"></div>

                    <div className="text-center font-bold text-xs text-emerald-700 uppercase">
                        *** LUNAS (PAID) ***
                    </div>
                    <div className="text-center text-[10px] text-gray-500">
                        Metode: {order.payment?.method || 'CASH'}
                        {order.payment?.cash_received && (
                            <>
                                <br />Bayar: Rp {order.payment.cash_received.toLocaleString('id-ID')}
                                <br />Kembali: Rp {order.payment.cash_change.toLocaleString('id-ID')}
                            </>
                        )}
                    </div>
                    <div className="text-center text-[9px] text-gray-400 mt-2">
                        Terima kasih atas kunjungan Anda!
                    </div>
                </div>

                <div className="flex gap-2 mt-4">
                    <button
                        onClick={handlePrint}
                        disabled={printing}
                        className="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-2 shadow"
                    >
                        <Printer className="h-4 w-4" />
                        {printing ? 'Memproses...' : 'Cetak Nota (Print)'}
                    </button>
                </div>
            </div>
        </div>
    );
}
