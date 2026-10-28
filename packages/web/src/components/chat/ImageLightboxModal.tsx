'use client';

import React, { useEffect, useRef, useState } from 'react';
import { ExternalLink, Maximize2, Minimize2, RotateCcw, X, ZoomIn, ZoomOut } from 'lucide-react';

interface ImageLightboxModalProps {
  src: string | null;
  onClose: () => void;
}

export default function ImageLightboxModal({ src, onClose }: ImageLightboxModalProps) {
  const [zoom, setZoom] = useState<number>(1);
  const [fitMode, setFitMode] = useState<boolean>(true);
  const [dragging, setDragging] = useState<boolean>(false);
  const dragStartRef = useRef<{ x: number; y: number; scrollLeft: number; scrollTop: number }>({
    x: 0,
    y: 0,
    scrollLeft: 0,
    scrollTop: 0,
  });
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setZoom(1);
    setFitMode(true);
  }, [src]);

  useEffect(() => {
    if (!src) return;
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === '+' || e.key === '=') {
        e.preventDefault();
        handleZoomIn();
      }
      if (e.key === '-') {
        e.preventDefault();
        handleZoomOut();
      }
      if (e.key === '0') {
        e.preventDefault();
        handleReset();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [src, fitMode, zoom]);

  if (!src) return null;

  const handleZoomIn = () => {
    if (fitMode) {
      setFitMode(false);
      setZoom(1.25);
    } else {
      setZoom((prev) => Math.min(4, +(prev + 0.25).toFixed(2)));
    }
  };

  const handleZoomOut = () => {
    if (fitMode) {
      setFitMode(false);
      setZoom(0.75);
    } else {
      setZoom((prev) => Math.max(0.25, +(prev - 0.25).toFixed(2)));
    }
  };

  const handleToggleFit = () => {
    if (fitMode) {
      setFitMode(false);
      setZoom(1);
    } else {
      setFitMode(true);
      setZoom(1);
    }
  };

  const handleReset = () => {
    setFitMode(true);
    setZoom(1);
  };

  const handleWheel = (e: React.WheelEvent<HTMLDivElement>) => {
    if (e.ctrlKey || e.metaKey || e.altKey) {
      e.preventDefault();
      if (e.deltaY < 0) handleZoomIn();
      else handleZoomOut();
    }
  };

  const handleMouseDown = (e: React.MouseEvent<HTMLDivElement>) => {
    if (fitMode || !containerRef.current) return;
    setDragging(true);
    dragStartRef.current = {
      x: e.clientX,
      y: e.clientY,
      scrollLeft: containerRef.current.scrollLeft,
      scrollTop: containerRef.current.scrollTop,
    };
  };

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!dragging || !containerRef.current) return;
    e.preventDefault();
    const dx = e.clientX - dragStartRef.current.x;
    const dy = e.clientY - dragStartRef.current.y;
    containerRef.current.scrollLeft = dragStartRef.current.scrollLeft - dx;
    containerRef.current.scrollTop = dragStartRef.current.scrollTop - dy;
  };

  const handleMouseUp = () => {
    setDragging(false);
  };

  const btnStyle: React.CSSProperties = {
    background: 'rgba(255,255,255,0.12)',
    border: '1px solid rgba(255,255,255,0.18)',
    color: '#fff',
    borderRadius: '8px',
    padding: '6px 10px',
    display: 'inline-flex',
    alignItems: 'center',
    gap: '5px',
    fontSize: '12px',
    fontWeight: 600,
    cursor: 'pointer',
  };

  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.88)',
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
      }}
    >
      {/* Floating Zoom Toolbar */}
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '8px',
          padding: '8px 14px',
          borderRadius: '12px',
          background: 'rgba(15, 23, 42, 0.92)',
          border: '1px solid rgba(255,255,255,0.15)',
          boxShadow: '0 10px 30px rgba(0,0,0,0.6)',
          marginBottom: '12px',
          zIndex: 10000,
        }}
      >
        <button type="button" onClick={handleZoomOut} style={btnStyle} title="Thu nhỏ (-)">
          <ZoomOut size={15} />
        </button>

        <button
          type="button"
          onClick={handleToggleFit}
          style={{ ...btnStyle, minWidth: '68px', justifyContent: 'center' }}
          title="Chuyển đổi Vừa màn hình / Kích thước thật 100%"
        >
          {fitMode ? 'Fit' : `${Math.round(zoom * 100)}%`}
        </button>

        <button type="button" onClick={handleZoomIn} style={btnStyle} title="Phóng to (+)">
          <ZoomIn size={15} />
        </button>

        <button
          type="button"
          onClick={handleToggleFit}
          style={btnStyle}
          title={fitMode ? 'Phóng 100% chiều ngang để đọc chữ' : 'Thu vừa khung màn hình'}
        >
          {fitMode ? <Maximize2 size={14} /> : <Minimize2 size={14} />}
          <span>{fitMode ? '100%' : 'Vừa khung'}</span>
        </button>

        <button type="button" onClick={handleReset} style={btnStyle} title="Đặt lại mặc định (0)">
          <RotateCcw size={14} />
        </button>

        <a
          href={src}
          target="_blank"
          rel="noreferrer"
          style={{ ...btnStyle, textDecoration: 'none', color: '#818CF8' }}
          title="Mở ảnh gốc trong tab mới"
        >
          <ExternalLink size={14} /> Tab mới
        </a>

        <button
          type="button"
          onClick={onClose}
          style={{ ...btnStyle, background: 'rgba(239, 68, 68, 0.25)', borderColor: 'rgba(239, 68, 68, 0.4)' }}
          title="Đóng (Esc)"
        >
          <X size={16} />
        </button>
      </div>

      {/* Scrollable / Pannable Image Viewport */}
      <div
        ref={containerRef}
        onClick={(e) => e.stopPropagation()}
        onWheel={handleWheel}
        onMouseDown={handleMouseDown}
        onMouseMove={handleMouseMove}
        onMouseUp={handleMouseUp}
        onMouseLeave={handleMouseUp}
        style={{
          position: 'relative',
          maxWidth: '95vw',
          maxHeight: 'calc(94vh - 64px)',
          overflow: 'auto',
          borderRadius: '10px',
          cursor: fitMode ? 'zoom-in' : dragging ? 'grabbing' : 'grab',
          display: 'flex',
          justifyContent: fitMode ? 'center' : 'flex-start',
          alignItems: fitMode ? 'center' : 'flex-start',
          background: '#0b0f19',
          boxShadow: '0 20px 50px rgba(0,0,0,0.85)',
        }}
      >
        <img
          src={src}
          alt="Preview"
          draggable={false}
          onClick={() => {
            if (fitMode) {
              setFitMode(false);
              setZoom(1);
            }
          }}
          style={
            fitMode
              ? {
                  maxWidth: '92vw',
                  maxHeight: 'calc(90vh - 68px)',
                  width: 'auto',
                  height: 'auto',
                  objectFit: 'contain',
                  display: 'block',
                  userSelect: 'none',
                }
              : {
                  width: `${Math.round(1080 * zoom)}px`,
                  maxWidth: 'none',
                  maxHeight: 'none',
                  height: 'auto',
                  display: 'block',
                  userSelect: 'none',
                }
          }
        />
      </div>
    </div>
  );
}
